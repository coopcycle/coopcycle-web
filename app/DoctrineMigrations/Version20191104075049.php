<?php declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20191104075049 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        $this->addSql('UPDATE address SET geo = ST_FlipCoordinates(geo::GEOMETRY)');
        $this->addSql('UPDATE bot SET last_position = ST_FlipCoordinates(last_position::GEOMETRY)');
        $this->addSql('UPDATE tracking_position SET coordinates = ST_FlipCoordinates(coordinates::GEOMETRY)');
    }

    public function down(Schema $schema) : void
    {
        $this->addSql('UPDATE address SET geo = ST_FlipCoordinates(geo::GEOMETRY)');
        $this->addSql('UPDATE bot SET last_position = ST_FlipCoordinates(last_position::GEOMETRY)');
        $this->addSql('UPDATE tracking_position SET coordinates = ST_FlipCoordinates(coordinates::GEOMETRY)');
    }
}
