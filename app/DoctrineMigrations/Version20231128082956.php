<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20231128082956 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE city_zone (id SERIAL NOT NULL, name TEXT DEFAULT NULL, polygon geography(POLYGON, 4326) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN city_zone.polygon IS \'(DC2Type:geojson)\'');
        $this->addSql('CREATE INDEX IDX_43A5FD6FC7A42112 ON city_zone USING gist(polygon)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE city_zone');
    }
}
