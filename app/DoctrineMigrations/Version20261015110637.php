<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Give task lists and tours an optimistic locking version.
 *
 * Existing rows start at 1, so a client that read a list before this migration
 * and writes after it simply fails the precondition once and refetches.
 */
final class Version20261015110637 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add a version column to task_list and tour';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE task_list ADD version INT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE tour ADD version INT DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE task_list DROP version');
        $this->addSql('ALTER TABLE tour DROP version');
    }
}
