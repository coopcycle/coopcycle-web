<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220819141352 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE woopit_integration (id SERIAL NOT NULL, store_id INT NOT NULL, zone_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, max_weight DOUBLE PRECISION DEFAULT NULL, max_length DOUBLE PRECISION DEFAULT NULL, max_height DOUBLE PRECISION DEFAULT NULL, max_width DOUBLE PRECISION DEFAULT NULL, woopit_store_id VARCHAR(255) NOT NULL, product_types JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_CB8A7ADAB092A811 ON woopit_integration (store_id)');
        $this->addSql('CREATE INDEX IDX_CB8A7ADA9F2C3FAB ON woopit_integration (zone_id)');
        $this->addSql('COMMENT ON COLUMN woopit_integration.product_types IS \'(DC2Type:json_array)\'');
        $this->addSql('ALTER TABLE woopit_integration ADD CONSTRAINT FK_CB8A7ADAB092A811 FOREIGN KEY (store_id) REFERENCES store (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE woopit_integration ADD CONSTRAINT FK_CB8A7ADA9F2C3FAB FOREIGN KEY (zone_id) REFERENCES zone (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE woopit_integration');
    }
}
