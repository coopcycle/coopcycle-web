<?php

namespace Application\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20170826133220 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE delivery_address (id SERIAL NOT NULL, name VARCHAR(255) DEFAULT NULL, geo geography(GEOMETRY, 4326) DEFAULT NULL, address_country VARCHAR(255) DEFAULT NULL, address_locality VARCHAR(255) DEFAULT NULL, address_region VARCHAR(255) DEFAULT NULL, postal_code VARCHAR(255) DEFAULT NULL, post_office_box_number VARCHAR(255) DEFAULT NULL, street_address VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN delivery_address.geo IS \'(DC2Type:geography)\'');
        $this->addSql('COMMENT ON COLUMN address.geo IS \'(DC2Type:geography)\'');
        $this->addSql('ALTER TABLE delivery ADD delivery_address_temp_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE delivery ADD CONSTRAINT FK_3781EC10A3CC6D58 FOREIGN KEY (delivery_address_temp_id) REFERENCES delivery_address (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_3781EC10A3CC6D58 ON delivery (delivery_address_temp_id)');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE delivery DROP CONSTRAINT FK_3781EC10A3CC6D58');
        $this->addSql('DROP TABLE delivery_address');
        $this->addSql('COMMENT ON COLUMN address.geo IS \'(DC2Type:geography)(DC2Type:geography)\'');
        $this->addSql('DROP INDEX UNIQ_3781EC10A3CC6D58');
        $this->addSql('ALTER TABLE delivery DROP delivery_address_temp_id');
    }
}
