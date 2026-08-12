<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Second data migration for the #874 / #5249 unassignment bug.
 *
 * Version20260804081026 repaired tasks that were still unassigned. But some
 * bug-unassigned tasks had already been re-assigned by hand (an admin working
 * around the bug) on the *old* code: that set `assigned_to` but filed the list
 * item on the wrong day (doneBefore, via #874) or left it orphaned, so the task
 * has `assigned_to` set yet shows nowhere on the board ("still unassigned").
 *
 * This migration is deliberately conservative. For a terminal (DONE/FAILED)
 * task that is currently assigned, matches the bug fingerprint (a
 * `task:unassigned` event dated after the task was completed), is NOT part of a
 * tour, and has NO valid list item (no item pointing to an existing task list),
 * it:
 *   - drops any orphaned list items (parent_id IS NULL) it may have,
 *   - (re)creates a single list item in the assigned courier's list for the day
 *     the task was completed,
 *   - drops the erroneous `task:unassigned` events from the history.
 *
 * It never changes `assigned_to` (the current courier is kept), and never
 * touches tasks that already have a valid item, so it will not move correctly
 * placed items nor create duplicates. Idempotent and safe to re-run.
 */
final class Version20260804085133 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Repair list items for bug-unassigned tasks that were later re-assigned but show nowhere (#874 / #5249).';
    }

    public function up(Schema $schema): void
    {
        $this->skipIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'This data migration only supports PostgreSQL.'
        );

        // 1. Collect the impacted tasks: terminal, currently assigned, bug
        //    fingerprint, not in a tour, and with no valid list item.
        $this->addSql(<<<'SQL'
            CREATE TEMPORARY TABLE _bug874_noitem AS
            SELECT t.id AS task_id,
                   t.assigned_to AS courier_id,
                   (
                       SELECT max(e.created_at)::date
                       FROM task_event e
                       WHERE e.task_id = t.id
                         AND e.name IN ('task:done', 'task:failed')
                   ) AS list_date
            FROM task t
            WHERE t.status IN ('DONE', 'FAILED')
              AND t.assigned_to IS NOT NULL
              AND EXISTS (
                  SELECT 1 FROM task_event un
                  WHERE un.task_id = t.id
                    AND un.name = 'task:unassigned'
                    AND EXISTS (
                        SELECT 1 FROM task_event dn
                        WHERE dn.task_id = t.id
                          AND dn.name IN ('task:done', 'task:failed')
                          AND dn.created_at <= un.created_at
                    )
              )
              -- a task inside a tour is listed through the tour, not directly;
              -- note task_collection_item is shared with deliveries, so filter
              -- on the collection type.
              AND NOT EXISTS (
                  SELECT 1
                  FROM task_collection_item tci
                  JOIN task_collection tc ON tc.id = tci.parent_id
                  WHERE tci.task_id = t.id
                    AND tc.type = 'tour'
              )
              AND NOT EXISTS (
                  SELECT 1
                  FROM task_list_item i
                  JOIN task_list tl ON tl.id = i.parent_id
                  WHERE i.task_id = t.id
              )
        SQL);

        // 2. Drop orphaned (detached) list items these tasks may still carry.
        $this->addSql(<<<'SQL'
            DELETE FROM task_list_item i
            USING _bug874_noitem r
            WHERE i.task_id = r.task_id
              AND i.parent_id IS NULL
        SQL);

        // 3. Make sure a task list exists for each (courier, completion day).
        $this->addSql(<<<'SQL'
            INSERT INTO task_list (courier_id, date, distance, duration, polyline, created_at, updated_at)
            SELECT DISTINCT r.courier_id, r.list_date, 0, 0, '', now(), now()
            FROM _bug874_noitem r
            WHERE r.list_date IS NOT NULL
            ON CONFLICT (date, courier_id) DO NOTHING
        SQL);

        // 4. Create the missing list item, appended after any existing ones.
        $this->addSql(<<<'SQL'
            INSERT INTO task_list_item (parent_id, task_id, position)
            SELECT tl.id,
                   r.task_id,
                   COALESCE(base.max_pos, -1)
                     + ROW_NUMBER() OVER (PARTITION BY tl.id ORDER BY r.task_id)
            FROM _bug874_noitem r
            JOIN task_list tl ON tl.courier_id = r.courier_id AND tl.date = r.list_date
            LEFT JOIN LATERAL (
                SELECT MAX(existing.position) AS max_pos
                FROM task_list_item existing
                WHERE existing.parent_id = tl.id
            ) base ON true
            WHERE r.list_date IS NOT NULL
              AND NOT EXISTS (
                  SELECT 1 FROM task_list_item y
                  WHERE y.task_id = r.task_id AND y.parent_id = tl.id
              )
        SQL);

        // 5. Clean up the history: drop the erroneous post-completion
        //    `task:unassigned` events for the repaired tasks.
        $this->addSql(<<<'SQL'
            DELETE FROM task_event ev
            USING _bug874_noitem r
            WHERE ev.task_id = r.task_id
              AND ev.name = 'task:unassigned'
              AND EXISTS (
                  SELECT 1 FROM task_event dn
                  WHERE dn.task_id = ev.task_id
                    AND dn.name IN ('task:done', 'task:failed')
                    AND dn.created_at <= ev.created_at
              )
        SQL);

        $this->addSql('DROP TABLE _bug874_noitem');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException(
            'Data repair of bug-unassigned tasks cannot be reversed.'
        );
    }
}
