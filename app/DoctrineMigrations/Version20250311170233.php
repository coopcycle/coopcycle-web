<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250311170233 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE api_app SET api_key = CONCAT('ak_', api_key) WHERE type = 'api_key'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE api_app SET api_key = REGEXP_REPLACE(api_key, 'ak_(.*)','\1') WHERE type = 'api_key'");
    }
}
