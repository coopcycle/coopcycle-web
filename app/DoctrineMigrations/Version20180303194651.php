<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180303194651 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs

        $this->addSql('DELETE FROM craue_config_setting WHERE name = :name AND value IS NULL', [
            'name' => 'stripe_publishable_key',
        ]);
        $this->addSql('DELETE FROM craue_config_setting WHERE name = :name AND value IS NULL', [
            'name' => 'stripe_secret_key',
        ]);
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
