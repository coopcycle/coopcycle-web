<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20171228153019 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE cart (id SERIAL NOT NULL, restaurant_id INT DEFAULT NULL, address_id INT DEFAULT NULL, distance INT DEFAULT NULL, date TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_BA388B7B1E7706E ON cart (restaurant_id)');
        $this->addSql('CREATE INDEX IDX_BA388B7F5B7AF75 ON cart (address_id)');
        $this->addSql('CREATE TABLE cart_item (id SERIAL NOT NULL, menu_item_id INT DEFAULT NULL, cart_id INT DEFAULT NULL, modifiers_description VARCHAR(255) NOT NULL, quantity INT NOT NULL, modifier_choices VARCHAR(255) NOT NULL, key VARCHAR(255) NOT NULL, unit_price DOUBLE PRECISION NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX UNIQ_F0FE25279AB44FE0 ON cart_item (menu_item_id)');
        $this->addSql('CREATE INDEX UNIQ_F0FE25271AD5CDBF ON cart_item (cart_id)');
        $this->addSql('ALTER TABLE cart ADD CONSTRAINT FK_BA388B7B1E7706E FOREIGN KEY (restaurant_id) REFERENCES restaurant (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE cart ADD CONSTRAINT FK_BA388B7F5B7AF75 FOREIGN KEY (address_id) REFERENCES address (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE cart_item ADD CONSTRAINT FK_F0FE25279AB44FE0 FOREIGN KEY (menu_item_id) REFERENCES menu_item (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE cart_item ADD CONSTRAINT FK_F0FE25271AD5CDBF FOREIGN KEY (cart_id) REFERENCES cart (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE cart_item DROP CONSTRAINT FK_F0FE25271AD5CDBF');
        $this->addSql('DROP TABLE cart');
        $this->addSql('DROP TABLE cart_item');
    }
}
