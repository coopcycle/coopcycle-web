<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Fourth data migration for the #874 / #5249 unassignment bug — final cleanup.
 *
 * Part A — wrong-day items.
 *   Some bug-affected tasks were re-listed on the wrong day (typically their
 *   doneBefore day, via #874) rather than the day they were completed. They are
 *   assigned and do appear on the board, just not where they should. For a
 *   terminal, assigned, non-tour task matching the bug fingerprint that has a
 *   list item but none in its (assigned courier, completion day) list, we move
 *   it there: drop its stray items and recreate a single one on the right day.
 *
 * Part B — orphaned items.
 *   The bug left detached task_list_item rows (parent_id IS NULL) that belong to
 *   no list. They are invisible junk; remove all of them.
 *
 * Both parts are idempotent and safe to re-run.
 */
final class Version20260804095916 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Move wrong-day list items to the completion day and sweep orphaned items (#874 / #5249).';
    }

    public function up(Schema $schema): void
    {
        // --- Part A: normalize wrong-day items to the completion day ---

        // Impacted: terminal, assigned, non-tour, bug fingerprint, has a valid
        // list item, but none in the (assigned courier, completion day) list.
        $this->addSql(<<<'SQL'
            CREATE TEMPORARY TABLE _bug874_wrongday AS
            SELECT t.id AS task_id,
                   t.assigned_to AS courier_id,
                   (
                       SELECT max(e.created_at)::date
                       FROM task_event e
                       WHERE e.task_id = t.id AND e.name IN ('task:done', 'task:failed')
                   ) AS list_date
            FROM task t
            WHERE t.status IN ('DONE', 'FAILED')
              AND t.assigned_to IS NOT NULL
              AND EXISTS (
                  SELECT 1 FROM task_event un
                  WHERE un.task_id = t.id AND un.name = 'task:unassigned'
                    AND EXISTS (
                        SELECT 1 FROM task_event dn
                        WHERE dn.task_id = t.id
                          AND dn.name IN ('task:done', 'task:failed')
                          AND dn.created_at <= un.created_at
                    )
              )
              AND NOT EXISTS (
                  SELECT 1 FROM task_collection_item tci
                  JOIN task_collection tc ON tc.id = tci.parent_id
                  WHERE tci.task_id = t.id AND tc.type = 'tour'
              )
              -- has at least one valid list item …
              AND EXISTS (
                  SELECT 1 FROM task_list_item i
                  JOIN task_list tl ON tl.id = i.parent_id
                  WHERE i.task_id = t.id
              )
              -- … but none on the (assigned courier, completion day) list
              AND NOT EXISTS (
                  SELECT 1 FROM task_list_item i
                  JOIN task_list tl ON tl.id = i.parent_id
                  WHERE i.task_id = t.id
                    AND tl.courier_id = t.assigned_to
                    AND tl.date = (
                        SELECT max(e.created_at)::date
                        FROM task_event e
                        WHERE e.task_id = t.id AND e.name IN ('task:done', 'task:failed')
                    )
              )
        SQL);

        // Drop the misplaced item(s) for those tasks.
        $this->addSql(<<<'SQL'
            DELETE FROM task_list_item dli
            USING _bug874_wrongday w
            WHERE dli.task_id = w.task_id
        SQL);

        // Make sure the (courier, completion day) list exists.
        $this->addSql(<<<'SQL'
            INSERT INTO task_list (courier_id, date, distance, duration, polyline, created_at, updated_at)
            SELECT DISTINCT w.courier_id, w.list_date, 0, 0, '', now(), now()
            FROM _bug874_wrongday w
            WHERE w.list_date IS NOT NULL
            ON CONFLICT (date, courier_id) DO NOTHING
        SQL);

        // Recreate a single item on the right day, appended after existing ones.
        $this->addSql(<<<'SQL'
            INSERT INTO task_list_item (parent_id, task_id, position)
            SELECT tl.id,
                   w.task_id,
                   COALESCE(base.max_pos, -1)
                     + ROW_NUMBER() OVER (PARTITION BY tl.id ORDER BY w.task_id)
            FROM _bug874_wrongday w
            JOIN task_list tl ON tl.courier_id = w.courier_id AND tl.date = w.list_date
            LEFT JOIN LATERAL (
                SELECT MAX(existing.position) AS max_pos
                FROM task_list_item existing
                WHERE existing.parent_id = tl.id
            ) base ON true
            WHERE w.list_date IS NOT NULL
              AND NOT EXISTS (
                  SELECT 1 FROM task_list_item y
                  WHERE y.task_id = w.task_id AND y.parent_id = tl.id
              )
        SQL);

        $this->addSql('DROP TABLE _bug874_wrongday');

        // --- Part B: sweep detached (orphaned) list items ---
        $this->addSql('DELETE FROM task_list_item WHERE parent_id IS NULL');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException(
            'Data repair of bug-affected task list items cannot be reversed.'
        );
    }
}
