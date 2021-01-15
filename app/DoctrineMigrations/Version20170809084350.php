<?php

namespace Application\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20170809084350 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE menu_section_base (id SERIAL NOT NULL, description VARCHAR(255) DEFAULT NULL, name VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');

        $this->addSql('DROP TABLE restaurant_product');
        $this->addSql('DROP TABLE menu_section_menu_item');
        $this->addSql('DROP TABLE menu_menu_section');

        $this->addSql('COMMENT ON COLUMN address.geo IS \'(DC2Type:geography)\'');

        $this->addSql('ALTER TABLE menu_item ADD parent_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE menu_item ADD CONSTRAINT FK_D754D550727ACA70 FOREIGN KEY (parent_id) REFERENCES menu_section (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_D754D550727ACA70 ON menu_item (parent_id)');

        $this->addSql('ALTER TABLE menu_section ADD menu_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE menu_section ADD menu_section_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE menu_section DROP description');
        $this->addSql('ALTER TABLE menu_section DROP name');
        $this->addSql('ALTER TABLE menu_section ADD CONSTRAINT FK_A5A86751CCD7E912 FOREIGN KEY (menu_id) REFERENCES menu (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE menu_section ADD CONSTRAINT FK_A5A86751F98E57A8 FOREIGN KEY (menu_section_id) REFERENCES menu_section_base (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_A5A86751CCD7E912 ON menu_section (menu_id)');
        $this->addSql('CREATE INDEX IDX_A5A86751F98E57A8 ON menu_section (menu_section_id)');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE menu_section DROP CONSTRAINT FK_A5A86751F98E57A8');

        $this->addSql('CREATE TABLE restaurant_product (restaurant_id INT NOT NULL, product_id INT NOT NULL, PRIMARY KEY(restaurant_id, product_id))');
        $this->addSql('CREATE INDEX idx_190158d84584665a ON restaurant_product (product_id)');
        $this->addSql('CREATE INDEX idx_190158d8b1e7706e ON restaurant_product (restaurant_id)');

        $this->addSql('CREATE TABLE menu_section_menu_item (menu_section_id INT NOT NULL, menu_item_id INT NOT NULL, PRIMARY KEY(menu_section_id, menu_item_id))');
        $this->addSql('CREATE INDEX idx_982775a69ab44fe0 ON menu_section_menu_item (menu_item_id)');
        $this->addSql('CREATE INDEX idx_982775a6f98e57a8 ON menu_section_menu_item (menu_section_id)');

        $this->addSql('CREATE TABLE menu_menu_section (menu_id INT NOT NULL, menu_section_id INT NOT NULL, PRIMARY KEY(menu_id, menu_section_id))');
        $this->addSql('CREATE INDEX idx_daaa96f4f98e57a8 ON menu_menu_section (menu_section_id)');
        $this->addSql('CREATE INDEX idx_daaa96f4ccd7e912 ON menu_menu_section (menu_id)');

        $this->addSql('ALTER TABLE topology.layer ADD CONSTRAINT layer_topology_id_fkey FOREIGN KEY (topology_id) REFERENCES topology.topology (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE restaurant_product ADD CONSTRAINT fk_190158d84584665a FOREIGN KEY (product_id) REFERENCES product (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE restaurant_product ADD CONSTRAINT fk_190158d8b1e7706e FOREIGN KEY (restaurant_id) REFERENCES restaurant (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE menu_section_menu_item ADD CONSTRAINT fk_982775a69ab44fe0 FOREIGN KEY (menu_item_id) REFERENCES menu_item (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE menu_section_menu_item ADD CONSTRAINT fk_982775a6f98e57a8 FOREIGN KEY (menu_section_id) REFERENCES menu_section (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE menu_menu_section ADD CONSTRAINT fk_daaa96f4f98e57a8 FOREIGN KEY (menu_section_id) REFERENCES menu_section (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE menu_menu_section ADD CONSTRAINT fk_daaa96f4ccd7e912 FOREIGN KEY (menu_id) REFERENCES menu (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('DROP TABLE menu_section_base');
        $this->addSql('COMMENT ON COLUMN address.geo IS \'(DC2Type:geography)(DC2Type:geography)\'');
        $this->addSql('ALTER TABLE menu_section DROP CONSTRAINT FK_A5A86751CCD7E912');
        $this->addSql('DROP INDEX IDX_A5A86751CCD7E912');
        $this->addSql('DROP INDEX IDX_A5A86751F98E57A8');
        $this->addSql('ALTER TABLE menu_section ADD description VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE menu_section ADD name VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE menu_section DROP menu_id');
        $this->addSql('ALTER TABLE menu_section DROP menu_section_id');
        $this->addSql('ALTER TABLE menu_item DROP CONSTRAINT FK_D754D550727ACA70');
        $this->addSql('DROP INDEX IDX_D754D550727ACA70');
        $this->addSql('ALTER TABLE menu_item DROP parent_id');
    }
}
