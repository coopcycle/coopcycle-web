<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Data migration: repair tasks wrongly unassigned by the #874 / #5249 bug.
 *
 * A completed task (DONE/FAILED) must never be unassigned, but the old
 * `set_items` / list-resolution code could strip the assignment off a finished
 * task, days after completion. The impacted tasks are now sitting with
 * `assigned_to = NULL` even though a `task:assigned` event exists after they
 * were completed.
 *
 * Fingerprint of an impacted task:
 *   - status is terminal (DONE or FAILED)
 *   - assigned_to IS NULL right now
 *   - it has a `task:unassigned` event dated after its `task:done`/`task:failed`
 *     event (a completed task can only be unassigned by the bug)
 *
 * For each such task we restore the courier and day from its most recent
 * `task:assigned` event (username + date), re-create the task list / list item
 * if needed, and drop the erroneous `task:unassigned` events from the history.
 *
 * The migration is idempotent (safe to re-run): it only touches tasks that are
 * currently unassigned, and never creates a duplicate task list or list item.
 */
final class Version20260804081026 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Repair tasks wrongly unassigned by the completed-task unassignment bug (#874 / #5249).';
    }

    public function up(Schema $schema): void
    {
        // 1. Collect the impacted tasks, resolving the courier + day to restore
        //    from the most recent `task:assigned` event.
        $this->addSql(<<<'SQL'
            CREATE TEMPORARY TABLE _bug874_impacted AS
            SELECT DISTINCT ON (t.id)
                t.id                    AS task_id,
                u.id                    AS courier_id,
                ev.created_at::date     AS list_date
            FROM task t
            JOIN task_event ev ON ev.task_id = t.id AND ev.name = 'task:assigned'
            JOIN api_user u ON u.username = ev.data->>'username'
            WHERE t.status IN ('DONE', 'FAILED')
              AND t.assigned_to IS NULL
              AND EXISTS (
                  SELECT 1
                  FROM task_event un
                  WHERE un.task_id = t.id
                    AND un.name = 'task:unassigned'
                    AND EXISTS (
                        SELECT 1
                        FROM task_event dn
                        WHERE dn.task_id = t.id
                          AND dn.name IN ('task:done', 'task:failed')
                          AND dn.created_at <= un.created_at
                    )
              )
            ORDER BY t.id, ev.created_at DESC
        SQL);

        // 2. Restore the assignment on the task itself.
        $this->addSql(<<<'SQL'
            UPDATE task t
            SET assigned_to = i.courier_id
            FROM _bug874_impacted i
            WHERE t.id = i.task_id
        SQL);

        // 3. Make sure a task list exists for each (courier, day) we restore.
        $this->addSql(<<<'SQL'
            INSERT INTO task_list (courier_id, date, distance, duration, polyline, created_at, updated_at)
            SELECT DISTINCT i.courier_id, i.list_date, 0, 0, '', now(), now()
            FROM _bug874_impacted i
            ON CONFLICT (date, courier_id) DO NOTHING
        SQL);

        // 4. Re-create the list item, but only for tasks that are not in any
        //    list anymore (never create a second item -> avoids the double
        //    listing #874 is about). Positions continue after the existing ones.
        $this->addSql(<<<'SQL'
            INSERT INTO task_list_item (parent_id, task_id, position)
            SELECT tl.id,
                   i.task_id,
                   COALESCE(base.max_pos, -1)
                     + ROW_NUMBER() OVER (PARTITION BY tl.id ORDER BY i.task_id)
            FROM _bug874_impacted i
            JOIN task_list tl ON tl.courier_id = i.courier_id AND tl.date = i.list_date
            LEFT JOIN LATERAL (
                SELECT MAX(existing.position) AS max_pos
                FROM task_list_item existing
                WHERE existing.parent_id = tl.id
            ) base ON true
            WHERE NOT EXISTS (
                SELECT 1 FROM task_list_item x WHERE x.task_id = i.task_id
            )
        SQL);

        // 5. Clean up the history: drop the erroneous `task:unassigned` events
        //    (those recorded after the task was already completed) for the
        //    restored tasks. The trailing `task:assigned` event is kept, so the
        //    history stays consistent with the restored assigned state.
        $this->addSql(<<<'SQL'
            DELETE FROM task_event ev
            USING _bug874_impacted i
            WHERE ev.task_id = i.task_id
              AND ev.name = 'task:unassigned'
              AND EXISTS (
                  SELECT 1
                  FROM task_event dn
                  WHERE dn.task_id = ev.task_id
                    AND dn.name IN ('task:done', 'task:failed')
                    AND dn.created_at <= ev.created_at
              )
        SQL);

        $this->addSql('DROP TABLE _bug874_impacted');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException(
            'Data repair of bug-unassigned tasks cannot be reversed.'
        );
    }
}
