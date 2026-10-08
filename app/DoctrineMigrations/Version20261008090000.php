<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261008090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove Standtrack integration (store_gln, company_gln setting, IUB sequence)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE store DROP store_gln');
        $this->addSql("DELETE FROM craue_config_setting WHERE name = 'company_gln'");
        $this->addSql('DROP SEQUENCE IF EXISTS standtrack_iub_seq');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE store ADD store_gln VARCHAR(255) DEFAULT NULL');
    }
}
