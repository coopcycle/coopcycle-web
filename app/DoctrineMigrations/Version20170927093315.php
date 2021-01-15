<?php

namespace Application\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20170927093315 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE modifier (id SERIAL NOT NULL, menu_item_modifier_id INT DEFAULT NULL, price DOUBLE PRECISION DEFAULT NULL, description VARCHAR(255) DEFAULT NULL, name VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_ABBFD9FDC2E539A ON modifier (menu_item_modifier_id)');
        $this->addSql('ALTER TABLE modifier ADD CONSTRAINT FK_ABBFD9FDC2E539A FOREIGN KEY (menu_item_modifier_id) REFERENCES menu_item_modifier (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('DROP TABLE menu_item_modifier_menu_item');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE menu_item_modifier_menu_item (menu_item_modifier_id INT NOT NULL, menu_item_id INT NOT NULL, PRIMARY KEY(menu_item_modifier_id, menu_item_id))');
        $this->addSql('CREATE INDEX idx_fdd5fac8c2e539a ON menu_item_modifier_menu_item (menu_item_modifier_id)');
        $this->addSql('CREATE INDEX idx_fdd5fac89ab44fe0 ON menu_item_modifier_menu_item (menu_item_id)');
        $this->addSql('ALTER TABLE menu_item_modifier_menu_item ADD CONSTRAINT fk_fdd5fac8c2e539a FOREIGN KEY (menu_item_modifier_id) REFERENCES menu_item_modifier (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE menu_item_modifier_menu_item ADD CONSTRAINT fk_fdd5fac89ab44fe0 FOREIGN KEY (menu_item_id) REFERENCES menu_item (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('DROP TABLE modifier');
    }
}
