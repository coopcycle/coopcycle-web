<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180510114932 extends AbstractMigration implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    private $locale;
    private $productOptionByCodeStmt;

    private function getProductOptionStrategy($calculusStrategy)
    {
        switch ($calculusStrategy) {
            case 'FREE':
                return 'free';
            case 'ADD_MENUITEM_PRICE':
                return 'option';
            case 'ADD_MODIFIER_PRICE':
                return 'option_value';
        }
    }

    private function insertOrUpdateProductOption($code, $name, $strategy, $price)
    {
        $this->productOptionByCodeStmt->bindParam('code', $code);
        $this->productOptionByCodeStmt->execute();

        if ($this->productOptionByCodeStmt->rowCount() === 0) {
            $this->addSql('INSERT INTO sylius_product_option (code, position, strategy, price, created_at, updated_at) VALUES (:code, 1, :strategy, :price, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)', [
                    'code' => $code,
                    'strategy' => $strategy,
                    'price' => $price
                ]);
            $this->addSql('INSERT INTO sylius_product_option_translation (translatable_id, name, locale) SELECT currval(\'sylius_product_option_id_seq\'), :name, :locale', [
                    'name' => $name,
                    'locale' => $this->locale,
                ]);
        } else {
            $this->addSql('UPDATE sylius_product_option SET strategy = :strategy, price = :price WHERE code = :code', [
                'code' => $code,
                'strategy' => $strategy,
                'price' => $price
            ]);
        }
    }

    private function insertOrUpdateProductOptionValue($optionCode, $code, $name, $price)
    {
        $this->productOptionValueByCodeStmt->bindParam('code', $code);
        $this->productOptionValueByCodeStmt->execute();

        if ($this->productOptionValueByCodeStmt->rowCount() === 0) {
            $this->addSql('INSERT INTO sylius_product_option_value (option_id, code, price) SELECT id, :code, :price FROM sylius_product_option WHERE code = :option_code', [
                    'option_code' => $optionCode,
                    'code' => $code,
                    'price' => $price
                ]);
            $this->addSql('INSERT INTO sylius_product_option_value_translation (translatable_id, value, locale) SELECT currval(\'sylius_product_option_value_id_seq\'), :name, :locale', [
                    'name' => $name,
                    'locale' => $this->locale,
                ]);
        } else {
            $this->addSql('UPDATE sylius_product_option_value SET price = :price WHERE code = :code', [
                'code' => $code,
                'price' => $price
            ]);
        }
    }

    private function findRestaurantByMenuItem($menuItemId)
    {
        $this->restaurantByMenuItemStmt->bindParam('menu_item_id', $menuItemId);
        $result = $this->restaurantByMenuItemStmt->execute();

        return $result->fetchAssociative();
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE sylius_product_option ADD strategy VARCHAR(255) DEFAULT \'free\' NOT NULL');
        $this->addSql('ALTER TABLE sylius_product_option ADD price INT DEFAULT NULL');
        $this->addSql('ALTER TABLE sylius_product_option_value ADD price INT DEFAULT NULL');
        $this->addSql('CREATE TABLE restaurant_product_option (restaurant_id INT NOT NULL, option_id INT NOT NULL, PRIMARY KEY(restaurant_id, option_id))');
        $this->addSql('CREATE INDEX IDX_C713F7D5B1E7706E ON restaurant_product_option (restaurant_id)');
        $this->addSql('CREATE INDEX IDX_C713F7D5A7C41D6F ON restaurant_product_option (option_id)');
        $this->addSql('ALTER TABLE restaurant_product_option ADD CONSTRAINT FK_C713F7D5B1E7706E FOREIGN KEY (restaurant_id) REFERENCES restaurant (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE restaurant_product_option ADD CONSTRAINT FK_C713F7D5A7C41D6F FOREIGN KEY (option_id) REFERENCES sylius_product_option (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

        $stmt = [];
        $stmt['menu_item'] =
            $this->connection->prepare('SELECT * FROM menu_item');
        $stmt['menu_item_modifier'] =
            $this->connection->prepare('SELECT * FROM menu_item_modifier WHERE menu_item_id = :menu_item_id');
        $stmt['modifier'] =
            $this->connection->prepare('SELECT * FROM modifier WHERE menu_item_modifier_id = :menu_item_modifier_id');

        $this->productOptionByCodeStmt =
            $this->connection->prepare('SELECT * FROM sylius_product_option WHERE code = :code');
        $this->productOptionValueByCodeStmt =
            $this->connection->prepare('SELECT * FROM sylius_product_option_value WHERE code = :code');
        $this->restaurantByMenuItemStmt =
            $this->connection->prepare('SELECT restaurant.id FROM menu_item JOIN menu_section ON menu_item.parent_id = menu_section.id JOIN menu ON menu.id = menu_section.menu_id JOIN restaurant ON restaurant.menu_id = menu.id WHERE menu_item.id = :menu_item_id');

        $this->locale = $this->container->getParameter('coopcycle.locale');

        $result = $stmt['menu_item']->execute();
        while ($menuItem = $result->fetchAssociative()) {

            $stmt['menu_item_modifier']->bindParam('menu_item_id', $menuItem['id']);
            $result2 = $stmt['menu_item_modifier']->execute();

            $restaurant = $this->findRestaurantByMenuItem($menuItem['id']);

            if (!$restaurant) {
                continue;
            }

            // A MenuItemModifier is actually a ProductOption
            while ($menuItemModifier = $result2->fetchAssociative()) {

                $stmt['modifier']->bindParam('menu_item_modifier_id', $menuItemModifier['id']);
                $result3 = $stmt['modifier']->execute();

                $productOptionCode = sprintf('CPCCL-FDTCH-%d-OPT-%d', $menuItem['id'], $menuItemModifier['id']);
                $productOptionName = $menuItemModifier['name'];
                $productOptionStrategy = $this->getProductOptionStrategy($menuItemModifier['calculus_strategy']);
                $productOptionPrice = null;
                if ($menuItemModifier['calculus_strategy'] === 'ADD_MENUITEM_PRICE') {
                    $productOptionPrice = (int) $menuItemModifier['price'] * 100;
                }

                $this->insertOrUpdateProductOption(
                    $productOptionCode,
                    $productOptionName,
                    $productOptionStrategy,
                    $productOptionPrice
                );

                $this->addSql('INSERT INTO restaurant_product_option (restaurant_id, option_id) SELECT :restaurant_id, id FROM sylius_product_option WHERE code = :code', [
                    'restaurant_id' => $restaurant['id'],
                    'code' => $productOptionCode
                ]);

                // A Modifier is actually a ProductOptionValue
                while ($modifier = $result3->fetchAssociative()) {

                    $productOptionValueCode = sprintf('%s-%d', $productOptionCode, $modifier['id']);
                    $productOptionValueName = $modifier['name'];
                    $productOptionValuePrice = null;
                    if ($menuItemModifier['calculus_strategy'] === 'ADD_MODIFIER_PRICE') {
                        $productOptionValuePrice = (int) $modifier['price'] * 100;
                    }

                    $this->insertOrUpdateProductOptionValue(
                        $productOptionCode,
                        $productOptionValueCode,
                        $productOptionValueName,
                        $productOptionValuePrice
                    );
                }
            }
        }
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE sylius_product_option DROP strategy');
        $this->addSql('ALTER TABLE sylius_product_option DROP price');
        $this->addSql('ALTER TABLE sylius_product_option_value DROP price');
        $this->addSql('DROP TABLE restaurant_product_option');
    }
}
