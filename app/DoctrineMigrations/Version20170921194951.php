<?php

namespace Application\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20170921194951 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE menu_item_modifier (id SERIAL NOT NULL, menu_item_id INT DEFAULT NULL, calculus_strategy VARCHAR(255) NOT NULL, price DOUBLE PRECISION NOT NULL, description VARCHAR(255) DEFAULT NULL, name VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_617144A09AB44FE0 ON menu_item_modifier (menu_item_id)');
        $this->addSql('CREATE TABLE menu_item_modifier_menu_item (menu_item_modifier_id INT NOT NULL, menu_item_id INT NOT NULL, PRIMARY KEY(menu_item_modifier_id, menu_item_id))');
        $this->addSql('CREATE INDEX IDX_FDD5FAC8C2E539A ON menu_item_modifier_menu_item (menu_item_modifier_id)');
        $this->addSql('CREATE INDEX IDX_FDD5FAC89AB44FE0 ON menu_item_modifier_menu_item (menu_item_id)');
        $this->addSql('CREATE TABLE order_item_modifier (id SERIAL NOT NULL, order_item_id INT DEFAULT NULL, modifier_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, description VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_6AD159E4E415FB15 ON order_item_modifier (order_item_id)');
        $this->addSql('CREATE INDEX IDX_6AD159E4D079F553 ON order_item_modifier (modifier_id)');
        $this->addSql('ALTER TABLE menu_item_modifier ADD CONSTRAINT FK_617144A09AB44FE0 FOREIGN KEY (menu_item_id) REFERENCES menu_item (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE menu_item_modifier_menu_item ADD CONSTRAINT FK_FDD5FAC8C2E539A FOREIGN KEY (menu_item_modifier_id) REFERENCES menu_item_modifier (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE menu_item_modifier_menu_item ADD CONSTRAINT FK_FDD5FAC89AB44FE0 FOREIGN KEY (menu_item_id) REFERENCES menu_item (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE order_item_modifier ADD CONSTRAINT FK_6AD159E4E415FB15 FOREIGN KEY (order_item_id) REFERENCES order_item (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE order_item_modifier ADD CONSTRAINT FK_6AD159E4D079F553 FOREIGN KEY (modifier_id) REFERENCES menu_item_modifier (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE menu_item_modifier_menu_item DROP CONSTRAINT FK_FDD5FAC8C2E539A');
        $this->addSql('ALTER TABLE order_item_modifier DROP CONSTRAINT FK_6AD159E4D079F553');
        $this->addSql('DROP TABLE menu_item_modifier');
        $this->addSql('DROP TABLE menu_item_modifier_menu_item');
        $this->addSql('DROP TABLE order_item_modifier');
    }
}
