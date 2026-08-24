<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Fifth data migration for the #874 / #5249 unassignment bug — history cleanup.
 *
 * The bug recorded `task:unassigned` events on tasks that were already
 * completed. A completed task can only be unassigned by the bug, so those
 * events are spurious. The earlier repair migrations only cleaned the history
 * of the tasks they had to fix; tasks whose data was already correct (assigned,
 * item on the right day) still carry the bogus `task:unassigned` line.
 *
 * This removes every `task:unassigned` event that was recorded at/after a
 * `task:done`/`task:failed` event, for tasks that are currently terminal and
 * assigned. Restricting to currently-assigned tasks keeps the remaining history
 * consistent with the task state (it ends on the `task:assigned` that is still
 * in effect); a task that is legitimately unassigned now keeps its event.
 *
 * Idempotent and safe to re-run.
 */
final class Version20260804105619 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove spurious task:unassigned events left on completed, still-assigned tasks (#874 / #5249).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DELETE FROM task_event ev
            USING task t
            WHERE ev.task_id = t.id
              AND ev.name = 'task:unassigned'
              AND t.status IN ('DONE', 'FAILED')
              AND t.assigned_to IS NOT NULL
              AND EXISTS (
                  SELECT 1 FROM task_event dn
                  WHERE dn.task_id = ev.task_id
                    AND dn.name IN ('task:done', 'task:failed')
                    AND dn.created_at <= ev.created_at
              )
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException(
            'Deleted task:unassigned history events cannot be restored.'
        );
    }
}
