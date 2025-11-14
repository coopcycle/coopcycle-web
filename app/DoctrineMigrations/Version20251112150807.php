<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251112150807 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add deleted_at to address to soft delete';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE address ADD deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE address DROP deleted_at');
    }
}
