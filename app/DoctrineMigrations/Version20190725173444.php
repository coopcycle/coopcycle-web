<?php declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20190725173444 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        $this->addSql('INSERT INTO craue_config_setting (section, name, value) VALUES (\'general\', \'enable_restaurant_pledges\', \'no\')');
    }

    public function down(Schema $schema) : void
    {
        $this->addSql('DELETE FROM craue_config_setting WHERE name = \'enable_restaurant_pledges\'');
    }
}
