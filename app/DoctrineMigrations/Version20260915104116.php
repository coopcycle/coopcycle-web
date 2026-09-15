<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260915104116 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove Facebook login (drop api_user.facebook_id and api_user.facebook_access_token)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE api_user DROP facebook_id');
        $this->addSql('ALTER TABLE api_user DROP facebook_access_token');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE api_user ADD facebook_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE api_user ADD facebook_access_token VARCHAR(255) DEFAULT NULL');
    }
}
