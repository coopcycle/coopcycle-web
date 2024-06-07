<?php

namespace Application\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20170805083706 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE menu (id SERIAL NOT NULL, description VARCHAR(255) DEFAULT NULL, name VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE menu_menu_section (menu_id INT NOT NULL, menu_section_id INT NOT NULL, PRIMARY KEY(menu_id, menu_section_id))');
        $this->addSql('CREATE INDEX IDX_DAAA96F4CCD7E912 ON menu_menu_section (menu_id)');
        $this->addSql('CREATE INDEX IDX_DAAA96F4F98E57A8 ON menu_menu_section (menu_section_id)');
        $this->addSql('CREATE TABLE menu_item (id SERIAL NOT NULL, price DOUBLE PRECISION DEFAULT NULL, description VARCHAR(255) DEFAULT NULL, name VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE menu_section (id SERIAL NOT NULL, description VARCHAR(255) DEFAULT NULL, name VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE menu_section_menu_item (menu_section_id INT NOT NULL, menu_item_id INT NOT NULL, PRIMARY KEY(menu_section_id, menu_item_id))');
        $this->addSql('CREATE INDEX IDX_982775A6F98E57A8 ON menu_section_menu_item (menu_section_id)');
        $this->addSql('CREATE INDEX IDX_982775A69AB44FE0 ON menu_section_menu_item (menu_item_id)');
        $this->addSql('ALTER TABLE menu_menu_section ADD CONSTRAINT FK_DAAA96F4CCD7E912 FOREIGN KEY (menu_id) REFERENCES menu (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE menu_menu_section ADD CONSTRAINT FK_DAAA96F4F98E57A8 FOREIGN KEY (menu_section_id) REFERENCES menu_section (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE menu_section_menu_item ADD CONSTRAINT FK_982775A6F98E57A8 FOREIGN KEY (menu_section_id) REFERENCES menu_section (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE menu_section_menu_item ADD CONSTRAINT FK_982775A69AB44FE0 FOREIGN KEY (menu_item_id) REFERENCES menu_item (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE restaurant ADD menu_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE restaurant ADD CONSTRAINT FK_EB95123FCCD7E912 FOREIGN KEY (menu_id) REFERENCES menu (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_EB95123FCCD7E912 ON restaurant (menu_id)');

        $sections = ['Entrées', 'Plats', 'Desserts'];

        $stmt = [];

        $stmt['restaurant'] = $this->connection->prepare("SELECT * FROM restaurant");
        $result = $stmt['restaurant']->execute();

        $sql = "SELECT * FROM product p "
             . "JOIN restaurant_product rp ON p.id = rp.product_id "
             . "WHERE rp.restaurant_id = ?";
        $stmt['product'] = $this->connection->prepare($sql);

        // Create the menu sections
        foreach ($sections as $section) {
            $this->addSql("INSERT INTO menu_section (name) VALUES (:name)", ['name' => $section]);
        }

        while ($restaurant = $result->fetchAssociative()) {

            // Create the menu
            $this->addSql("INSERT INTO menu (name) VALUES (:name)", $restaurant);
            $this->addSql("UPDATE restaurant SET menu_id = currval('menu_id_seq') WHERE id = :id", $restaurant);

            // Associate the menu sections
            foreach ($sections as $section) {
                $this->addSql("INSERT INTO menu_menu_section (menu_id, menu_section_id) SELECT currval('menu_id_seq'), id FROM menu_section WHERE name = :name", ['name' => $section]);
            }

            $stmt['product']->bindParam(1, $restaurant['id']);
            $result2 = $stmt['product']->execute();

            while ($product = $result2->fetchAssociative()) {

                // Create the menu item
                $this->addSql("INSERT INTO menu_item (name, price) VALUES (:name, :price)", $product);

                // Associate the menu item to section
                $this->addSql("INSERT INTO menu_section_menu_item (menu_section_id, menu_item_id) "
                    . "SELECT id, currval('menu_item_id_seq') FROM menu_section WHERE name = :name", [
                    'name' => $product['recipe_category']
                ]);
            }
        }
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE menu_menu_section DROP CONSTRAINT FK_DAAA96F4CCD7E912');
        $this->addSql('ALTER TABLE restaurant DROP CONSTRAINT FK_EB95123FCCD7E912');
        $this->addSql('ALTER TABLE menu_section_menu_item DROP CONSTRAINT FK_982775A69AB44FE0');
        $this->addSql('ALTER TABLE menu_menu_section DROP CONSTRAINT FK_DAAA96F4F98E57A8');
        $this->addSql('ALTER TABLE menu_section_menu_item DROP CONSTRAINT FK_982775A6F98E57A8');
        $this->addSql('DROP TABLE menu');
        $this->addSql('DROP TABLE menu_menu_section');
        $this->addSql('DROP TABLE menu_item');
        $this->addSql('DROP TABLE menu_section');
        $this->addSql('DROP TABLE menu_section_menu_item');
        $this->addSql('DROP INDEX UNIQ_EB95123FCCD7E912');
        $this->addSql('ALTER TABLE restaurant DROP menu_id');
    }
}
