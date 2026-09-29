<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260929120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Index task(done_before, done_after) to support the dispatch date filter';
    }

    /**
     * CREATE INDEX CONCURRENTLY cannot run inside a transaction, and `task` is
     * a large, write-heavy table in production, where a plain CREATE INDEX
     * would block writes for the duration of the build.
     */
    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        // AppBundle\Api\Filter\TaskDateFilter selects the tasks covering a given
        // day with `done_after < :startOfNextDay AND done_before >= :startOfDay`.
        // done_before leads: for the dates the dispatch actually looks at, it
        // prunes every task of every past day, and done_after is then evaluated
        // from the index instead of the heap.
        $this->addSql(
            'CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_task_done_before_done_after
             ON task (done_before, done_after)'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX CONCURRENTLY IF EXISTS idx_task_done_before_done_after');
    }
}
