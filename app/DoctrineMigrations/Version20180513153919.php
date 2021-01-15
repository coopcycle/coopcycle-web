<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;
use Sylius\Component\Product\Generator\CartesianSetBuilder;

/**
 * This crazy migration creates all the ProductVariant with all the ProductOptionValue permutations.
 */
class Version20180513153919 extends AbstractMigration
{
    private $setBuilder;

    private function associateProductAndOption($productCode, $productOptionCode)
    {
        $this->productOptionsAssociationStmt->bindParam('product_code', $productCode);
        $this->productOptionsAssociationStmt->bindParam('option_code', $productOptionCode);
        $this->productOptionsAssociationStmt->execute();

        if ($this->productOptionsAssociationStmt->rowCount() === 0) {
            $this->addSql('INSERT INTO sylius_product_options (product_id, option_id) SELECT sylius_product.id, sylius_product_option.id FROM sylius_product, sylius_product_option WHERE sylius_product.code = :product_code AND sylius_product_option.code = :option_code', [
                'product_code' => $productCode,
                'option_code' => $productOptionCode,
            ]);
        }
    }

    private function createVariant($productCode, $modifierMap, $permutation, $taxCategoryId, $price)
    {
        $modifiersIds = [];
        if (!is_array($permutation)) {
            $modifierId = $modifierMap[$permutation];
            $modifiersIds[] = $modifierId;
        } else {
            foreach ($permutation as $code) {
                $modifierId = $modifierMap[$code];
                $modifiersIds[] = $modifierId;
            }
            sort($modifiersIds);
        }

        $productVariantCode = sprintf('%s-MOD-%s', $productCode, implode('-', $modifiersIds));

        $this->productVariantStmt->bindParam('code', $productVariantCode);
        $this->productVariantStmt->execute();

        if ($this->productVariantStmt->rowCount() === 0) {
            $this->addSql('INSERT INTO sylius_product_variant (product_id, code, created_at, updated_at, position, tax_category_id, price) SELECT id, :variant_code, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 1, :tax_category_id, :price FROM sylius_product WHERE code = :product_code', [
                'variant_code' => $productVariantCode,
                'product_code' => $productCode,
                'tax_category_id' => $taxCategoryId,
                'price' => (int) $price * 100,
            ]);
        }

        if (!is_array($permutation)) {
            $this->associateProductVariantAndOptionValue($productVariantCode, $permutation);
            return;
        }

        foreach ($permutation as $code) {
            $this->associateProductVariantAndOptionValue($productVariantCode, $code);
        }
    }

    private function associateProductVariantAndOptionValue($productVariantCode, $optionValueCode)
    {
        $this->productOptionValueAssociationStmt->bindParam('variant_code', $productVariantCode);
        $this->productOptionValueAssociationStmt->bindParam('option_value_code', $optionValueCode);
        $this->productOptionValueAssociationStmt->execute();

        if ($this->productVariantStmt->rowCount() === 0) {
            $this->addSql('INSERT INTO sylius_product_variant_option_value (variant_id, option_value_id) SELECT sylius_product_variant.id, sylius_product_option_value.id FROM sylius_product_variant, sylius_product_option_value WHERE sylius_product_variant.code = :variant_code AND sylius_product_option_value.code = :option_value_code', [
                'variant_code' => $productVariantCode,
                'option_value_code' => $optionValueCode,
            ]);
        }
    }

    public function up(Schema $schema) : void
    {
        $stmt = [];
        $stmt['menu_item'] =
            $this->connection->prepare('SELECT * FROM menu_item');
        $stmt['menu_item_modifier'] =
            $this->connection->prepare('SELECT * FROM menu_item_modifier WHERE menu_item_id = :menu_item_id');
        $stmt['modifier'] =
            $this->connection->prepare('SELECT * FROM modifier WHERE menu_item_modifier_id = :menu_item_modifier_id');
        $stmt['product_variant'] =
            $this->connection->prepare('SELECT sylius_product_variant.* FROM sylius_product_variant JOIN sylius_product ON sylius_product_variant.product_id = sylius_product.id WHERE sylius_product.code = :code');
        $stmt['product_variant_option_value'] =
            $this->connection->prepare('SELECT sylius_product_variant_option_value.* FROM sylius_product_variant_option_value JOIN sylius_product_variant ON sylius_product_variant_option_value.variant_id = sylius_product_variant.id WHERE sylius_product_variant.code = :code');

        $this->productOptionsAssociationStmt =
            $this->connection->prepare('SELECT * FROM sylius_product_options JOIN sylius_product ON sylius_product_options.product_id = sylius_product.id JOIN sylius_product_option ON sylius_product_options.option_id = sylius_product_option.id WHERE sylius_product.code = :product_code AND sylius_product_option.code = :option_code');

        $this->productVariantStmt =
            $this->connection->prepare('SELECT * FROM sylius_product_variant where code = :code');

        $this->productOptionValueAssociationStmt =
            $this->connection->prepare('SELECT sylius_product_variant_option_value.* FROM sylius_product_variant_option_value JOIN sylius_product_variant ON sylius_product_variant_option_value.variant_id = sylius_product_variant.id JOIN sylius_product_option_value ON sylius_product_variant_option_value.option_value_id = sylius_product_option_value.id WHERE sylius_product_variant.code = :variant_code AND sylius_product_option_value.code = :option_value_code');

        $this->setBuilder = new CartesianSetBuilder();

        $stmt['menu_item']->execute();
        while ($menuItem = $stmt['menu_item']->fetch()) {

            $productCode = sprintf('CPCCL-FDTCH-%d', $menuItem['id']);

            $stmt['menu_item_modifier']->bindParam('menu_item_id', $menuItem['id']);
            $stmt['menu_item_modifier']->execute();

            // Skip menu items with no menu item modifier
            if ($stmt['menu_item_modifier']->rowCount() === 0) {
                continue;
            }

            $optionSet = [];
            $modifierMap = []; // Maps product option code with modifier id

            $i = 0;
            while ($menuItemModifier = $stmt['menu_item_modifier']->fetch()) {

                $productOptionCode = sprintf('CPCCL-FDTCH-%d-OPT-%d', $menuItem['id'], $menuItemModifier['id']);

                $this->associateProductAndOption($productCode, $productOptionCode);

                $stmt['product_variant']->bindParam('code', $productCode);
                $stmt['product_variant']->execute();

                while ($productVariant = $stmt['product_variant']->fetch()) {

                    $stmt['product_variant_option_value']->bindParam('code', $productVariant['code']);
                    $stmt['product_variant_option_value']->execute();

                    // Delete default variant created on previous migration
                    if ($stmt['product_variant_option_value']->rowCount() === 0) {
                        // FIXME Find a way to change the variant instead of deleting it
                        $this->addSql('DELETE FROM sylius_order_item USING sylius_product_variant WHERE sylius_product_variant.id = sylius_order_item.variant_id AND sylius_product_variant.code = :code', [
                            'code' => $productVariant['code'],
                        ]);
                        $this->addSql('DELETE FROM sylius_product_variant WHERE code = :code', [
                            'code' => $productVariant['code'],
                        ]);
                    }
                }

                $stmt['modifier']->bindParam('menu_item_modifier_id', $menuItemModifier['id']);
                $stmt['modifier']->execute();

                $modifiersIds = [];
                while ($modifier = $stmt['modifier']->fetch()) {
                    $productOptionValueCode = sprintf('%s-%d', $productOptionCode, $modifier['id']);
                    $optionSet[$i][] = $productOptionValueCode;
                    $modifierMap[$productOptionValueCode] = $modifier['id'];
                }

                ++$i;
            }

            if (count($optionSet) === 0) {
                continue;
            }

            $permutations = $this->setBuilder->build($optionSet);

            foreach ($permutations as $permutation) {
                $this->createVariant($productCode, $modifierMap, $permutation, $menuItem['tax_category_id'], $menuItem['price']);
            }
        }
    }

    public function down(Schema $schema) : void
    {

    }
}
