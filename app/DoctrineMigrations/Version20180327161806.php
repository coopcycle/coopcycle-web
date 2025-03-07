<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180327161806 extends AbstractMigration implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    const PRODUCT_CODE = 'CPCCL-ODDLVR';

    private function findAllDeliveryOrderItems()
    {
        $stmt = $this->connection->prepare('SELECT delivery_order_item.order_item_id, delivery.vehicle, delivery.total_including_tax, task_collection.distance FROM delivery_order_item JOIN delivery ON delivery_order_item.delivery_id = delivery.id JOIN task_collection ON task_collection.id = delivery.id');
        $result = $stmt->execute();

        $deliveryOrderItems = [];
        while ($deliveryOrderItem = $result->fetchAssociative()) {
            $deliveryOrderItems[] = $deliveryOrderItem;
        }

        return $deliveryOrderItems;
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs

        $this->addSql('ALTER TABLE sylius_order_item ADD variant_id INT DEFAULT NULL');

        $doctrine = $this->container->get('doctrine');
        $settingsManager = $this->container->get('coopcycle.settings_manager');

        $productRepository = $this->container->get('sylius.repository.product');

        $defaultTaxCategoryCode = $settingsManager->get('default_tax_category');
        $taxCategory = $this->container
            ->get('sylius.repository.tax_category')
            ->findOneByCode($defaultTaxCategoryCode);

        $product = $productRepository->findOneByCode(self::PRODUCT_CODE);

        if (!$product) {
            $this->addSql('INSERT INTO sylius_product (code, enabled, created_at, updated_at) VALUES (:code, :enabled, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)', [
                'code' => self::PRODUCT_CODE,
                'enabled' => 't'
            ]);
            $this->addSql('INSERT INTO sylius_product_translation (translatable_id, name, slug, locale) SELECT id, :name, :slug, :locale FROM sylius_product WHERE code = :code', [
                'name' => 'Livraison à la demande',
                'slug' => 'livraison-a-la-demande',
                'locale' => 'fr',
                'code' => self::PRODUCT_CODE,
            ]);
        }

        foreach ($this->findAllDeliveryOrderItems() as $deliveryOrderItem) {

            $price = (int) ($deliveryOrderItem['total_including_tax'] * 100);

            $hash = sprintf('%s-%d-%d', $deliveryOrderItem['vehicle'], $deliveryOrderItem['distance'], $price);
            $code = sprintf('CPCCL-ODDLVR-%s', strtoupper(substr(sha1($hash), 0, 7)));
            $name = sprintf('Livraison %d km', number_format($deliveryOrderItem['distance'] / 1000, 2));

            $this->addSql('INSERT INTO sylius_product_variant (product_id, code, position, tax_category_id, price, created_at, updated_at) SELECT id, :code, :position, :tax_category_id, :price, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP FROM sylius_product WHERE code = :product_code', [
                'code' => $code,
                'position' => 1,
                'tax_category_id' => $taxCategory->getId(),
                'price' => $price,
                'product_code' => self::PRODUCT_CODE,
            ]);

            $this->addSql('INSERT INTO sylius_product_variant_translation (translatable_id, name, locale) SELECT id, :name, :locale FROM sylius_product_variant WHERE code = :code', [
                'name' => $name,
                'locale' => 'fr',
                'code' => $code,
            ]);

            $this->addSql('UPDATE sylius_order_item SET variant_id = (SELECT id FROM sylius_product_variant WHERE code = :code) WHERE id = :order_item_id', [
                'code' => $code,
                'order_item_id' => $deliveryOrderItem['order_item_id']
            ]);
        }

        $this->addSql('ALTER TABLE sylius_order_item ALTER COLUMN variant_id SET NOT NULL');
        $this->addSql('ALTER TABLE sylius_order_item ADD CONSTRAINT FK_77B587ED3B69A9AF FOREIGN KEY (variant_id) REFERENCES sylius_product_variant (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_77B587ED3B69A9AF ON sylius_order_item (variant_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

        $this->addSql('ALTER TABLE sylius_order_item DROP CONSTRAINT FK_77B587ED3B69A9AF');
        $this->addSql('DROP INDEX IDX_77B587ED3B69A9AF');
        $this->addSql('ALTER TABLE sylius_order_item DROP variant_id');

        $this->addSql('DELETE FROM sylius_product');
        $this->addSql('DELETE FROM sylius_product_variant');
    }
}
