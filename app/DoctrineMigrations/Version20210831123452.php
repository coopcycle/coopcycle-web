<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210831123452 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE api_app ADD type VARCHAR(12) DEFAULT NULL');
        $this->addSql('UPDATE api_app SET type = \'oauth\'');
        $this->addSql('ALTER TABLE api_app ALTER type SET NOT NULL');

        $this->addSql('ALTER TABLE api_app ADD api_key VARCHAR(255) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8AEC36FBC912ED9D ON api_app (api_key)');

        $this->addSql('ALTER TABLE api_app ALTER oauth2_client_id DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE api_app DROP type');

        $this->addSql('DROP INDEX UNIQ_8AEC36FBC912ED9D');
        $this->addSql('ALTER TABLE api_app DROP api_key');

        $this->addSql('ALTER TABLE api_app ALTER oauth2_client_id SET NOT NULL');
    }
}
