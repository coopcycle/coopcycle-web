<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180509130706 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE restaurant_product (restaurant_id INT NOT NULL, product_id INT NOT NULL, PRIMARY KEY(restaurant_id, product_id))');
        $this->addSql('CREATE INDEX IDX_190158D8B1E7706E ON restaurant_product (restaurant_id)');
        $this->addSql('CREATE INDEX IDX_190158D84584665A ON restaurant_product (product_id)');
        $this->addSql('ALTER TABLE restaurant_product ADD CONSTRAINT FK_190158D8B1E7706E FOREIGN KEY (restaurant_id) REFERENCES restaurant (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE restaurant_product ADD CONSTRAINT FK_190158D84584665A FOREIGN KEY (product_id) REFERENCES sylius_product (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

        $stmt = [];
        $stmt['restaurants'] =
            $this->connection->prepare('SELECT id, menu_id FROM restaurant');
        $stmt['menu_sections'] =
            $this->connection->prepare('SELECT id FROM menu_section WHERE menu_id = :menu_id');
        $stmt['menu_items'] =
            $this->connection->prepare('SELECT id FROM menu_item WHERE parent_id = :parent_id');
        $stmt['products'] =
            $this->connection->prepare('SELECT id FROM sylius_product WHERE code = :code');

        $result = $stmt['restaurants']->execute();
        while ($restaurant = $result->fetchAssociative()) {

            $stmt['menu_sections']->bindParam('menu_id', $restaurant['menu_id']);
            $result2 = $stmt['menu_sections']->execute();

            while ($menuSection = $result2->fetchAssociative()) {

                $stmt['menu_items']->bindParam('parent_id', $menuSection['id']);
                $result3 = $stmt['menu_items']->execute();

                while ($menuItem = $result3->fetchAssociative()) {

                    $productCode = sprintf('CPCCL-FDTCH-%d', $menuItem['id']);

                    $stmt['products']->bindParam('code', $productCode);
                    $result4 = $stmt['products']->execute();

                    while ($product = $result4->fetch()) {
                        $this->addSql('INSERT INTO restaurant_product (restaurant_id, product_id) VALUES (:restaurant_id, :product_id)', [
                            'restaurant_id' => $restaurant['id'],
                            'product_id' => $product['id']
                        ]);
                    }
                }
            }
        }
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE restaurant_product');
    }
}
