<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260506091239 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE api_user ADD email_auth_code VARCHAR(255) DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN city_zone.polygon IS \'(DC2Type:geojson)\'');
        $this->addSql('ALTER INDEX idx_2a41b5e5a5e3b32d RENAME TO IDX_F7FF7D4BB5B58DBB');
        $this->addSql('COMMENT ON COLUMN zone.polygon IS \'(DC2Type:geojson)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('CREATE SCHEMA tiger_data');
        $this->addSql('CREATE SCHEMA tiger');
        $this->addSql('CREATE SCHEMA topology');
        $this->addSql('COMMENT ON COLUMN city_zone.polygon IS \'(DC2Type:geojson)(DC2Type:geojson)\'');
        $this->addSql('COMMENT ON COLUMN zone.polygon IS \'(DC2Type:geojson)(DC2Type:geojson)\'');
        $this->addSql('ALTER INDEX idx_f7ff7d4bb5b58dbb RENAME TO idx_2a41b5e5a5e3b32d');
        $this->addSql('ALTER TABLE api_user DROP email_auth_code');
    }
}
