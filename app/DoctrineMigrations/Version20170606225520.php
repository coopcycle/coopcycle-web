<?php

namespace Application\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20170606225520 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP INDEX idx_restaurant_geo');
        $this->addSql('ALTER TABLE restaurant DROP geo');
        $this->addSql('ALTER TABLE restaurant DROP address_country');
        $this->addSql('ALTER TABLE restaurant DROP address_locality');
        $this->addSql('ALTER TABLE restaurant DROP address_region');
        $this->addSql('ALTER TABLE restaurant DROP postal_code');
        $this->addSql('ALTER TABLE restaurant DROP post_office_box_number');
        $this->addSql('ALTER TABLE restaurant DROP street_address');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE restaurant ADD geo geography(GEOMETRY, 4326) DEFAULT NULL');
        $this->addSql('ALTER TABLE restaurant ADD address_country VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE restaurant ADD address_locality VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE restaurant ADD address_region VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE restaurant ADD postal_code VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE restaurant ADD post_office_box_number VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE restaurant ADD street_address VARCHAR(255) DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN restaurant.geo IS \'(DC2Type:geography)\'');
        $this->addSql('CREATE INDEX idx_restaurant_geo ON restaurant USING gist(geo)');
    }
}
