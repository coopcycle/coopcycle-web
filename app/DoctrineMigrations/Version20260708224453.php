<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260708224453 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add comment field to shift';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shift ADD comment TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shift DROP comment');
    }
}
