<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Repair task list items filed on a day their task cannot be carried out on.
 *
 * A task whose `assigned_to` is set while its `task_list_item` lives on another
 * day's list renders nowhere on the dispatch board: it counts as neither
 * unassigned (it has a courier) nor part of any visible task list, so only the
 * search box finds it. Clients that PUT a set_items payload under the wrong date
 * produced such rows until the guard in TaskListManager::assign() shipped; this
 * migration cleans up what they left behind.
 *
 * Completed work keeps its assignment and moves to the list of the day it was
 * actually carried out on. Anything still to do goes back to the unassigned pool
 * of its own day, where a dispatcher can see it and assign it properly.
 *
 * NB: this is a data migration and runs its statements directly, so it reports
 * real row counts — but `--dry-run` will not hold it back.
 */
final class Version20260901090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Repair task list items filed outside their task\'s date window (executes immediately, not dry-run safe)';
    }

    public function up(Schema $schema): void
    {
        $this->connection->executeStatement(<<<'SQL'
            CREATE TEMP TABLE misfiled ON COMMIT DROP AS
            SELECT tli.id        AS item_id,
                   tli.parent_id AS source_list_id,
                   tl.courier_id,
                   tl.date       AS filed_on,
                   t.id          AS task_id,
                   t.status,
                   CASE WHEN tl.date < t.done_after::date
                        THEN t.done_after::date
                        ELSE t.done_before::date
                   END           AS target_date
            FROM task_list_item tli
            JOIN task_list tl ON tl.id = tli.parent_id
            JOIN task      t  ON t.id  = tli.task_id
            WHERE tli.task_id IS NOT NULL
              AND t.assigned_to = tl.courier_id
              AND (tl.date < t.done_after::date OR tl.date > t.done_before::date)
            SQL);

        $summary = $this->connection->fetchAssociative(<<<'SQL'
            SELECT COUNT(*)                                                    AS total,
                   COUNT(*) FILTER (WHERE status IN ('DONE', 'FAILED'))        AS completed,
                   COUNT(*) FILTER (WHERE status NOT IN ('DONE', 'FAILED'))    AS pending,
                   COUNT(DISTINCT courier_id)                                  AS couriers,
                   MIN(filed_on)                                               AS first_day,
                   MAX(filed_on)                                               AS last_day
            FROM misfiled
            SQL);

        if (0 === (int) $summary['total']) {
            $this->write('No misfiled task list items found, nothing to repair.');

            return;
        }

        $this->write(sprintf(
            'Found %d misfiled task list item(s) across %d courier(s), filed between %s and %s: %d completed, %d still to do.',
            $summary['total'],
            $summary['couriers'],
            $summary['first_day'],
            $summary['last_day'],
            $summary['completed'],
            $summary['pending']
        ));

        $this->repairCompleted();
        $this->repairPending();

        $bumped = $this->connection->executeStatement(<<<'SQL'
            UPDATE task_list SET updated_at = NOW()
            WHERE id IN (SELECT source_list_id FROM misfiled)
               OR (courier_id, date) IN (SELECT courier_id, target_date FROM misfiled)
            SQL);

        $this->write(sprintf('Touched %d task list(s) so clients refetch them.', $bumped));

        $left = $this->connection->fetchOne(<<<'SQL'
            SELECT COUNT(*)
            FROM task_list_item tli
            JOIN task_list tl ON tl.id = tli.parent_id
            JOIN task      t  ON t.id  = tli.task_id
            WHERE tli.task_id IS NOT NULL
              AND t.assigned_to = tl.courier_id
              AND (tl.date < t.done_after::date OR tl.date > t.done_before::date)
            SQL);

        if (0 === (int) $left) {
            $this->write('No misfiled task list items remain.');
        } else {
            $this->write(sprintf('WARNING: %d misfiled task list item(s) still remain.', $left));
        }
    }

    /**
     * Completed work is already done, so the assignment stands: move its item to
     * the list of the day it belongs to, creating that list when the courier has
     * none for the day.
     */
    private function repairCompleted(): void
    {
        $created = $this->connection->executeStatement(<<<'SQL'
            INSERT INTO task_list (courier_id, date, distance, duration, polyline, created_at, updated_at)
            SELECT DISTINCT m.courier_id, m.target_date, 0, 0, '', NOW(), NOW()
            FROM misfiled m
            WHERE m.status IN ('DONE', 'FAILED')
            ON CONFLICT (date, courier_id) DO NOTHING
            SQL);

        $this->write(sprintf('Created %d missing task list(s) for completed work.', $created));

        // task_list_item has no unique constraint on task_id (see the FIXME in
        // TaskList.Item.orm.xml), so a task may hold several items. Keep exactly
        // one per courier/day/task, otherwise the move below duplicates them.
        $this->connection->executeStatement(<<<'SQL'
            CREATE TEMP TABLE keep ON COMMIT DROP AS
            SELECT DISTINCT ON (m.courier_id, m.target_date, m.task_id) m.item_id
            FROM misfiled m
            WHERE m.status IN ('DONE', 'FAILED')
            ORDER BY m.courier_id, m.target_date, m.task_id, m.item_id
            SQL);

        $dropped = $this->connection->executeStatement(<<<'SQL'
            DELETE FROM task_list_item tli
            USING misfiled m
            WHERE tli.id = m.item_id
              AND m.status IN ('DONE', 'FAILED')
              AND (
                    -- redundant with another misfiled item for the same task and day
                    NOT EXISTS (SELECT 1 FROM keep k WHERE k.item_id = m.item_id)
                    -- or the destination list already lists this task
                    OR EXISTS (
                          SELECT 1
                          FROM task_list tl2
                          JOIN task_list_item dup ON dup.parent_id = tl2.id AND dup.task_id = m.task_id
                          WHERE tl2.courier_id = m.courier_id AND tl2.date = m.target_date
                    )
              )
            SQL);

        $this->write(sprintf('Dropped %d redundant item(s) for completed work.', $dropped));

        $moved = $this->connection->executeStatement(<<<'SQL'
            WITH moved AS (
                SELECT m.item_id,
                       tl2.id AS target_list_id,
                       COALESCE((SELECT MAX(x.position) FROM task_list_item x WHERE x.parent_id = tl2.id), -1)
                         + ROW_NUMBER() OVER (PARTITION BY tl2.id ORDER BY m.item_id) AS new_position
                FROM misfiled m
                JOIN task_list tl2 ON tl2.courier_id = m.courier_id AND tl2.date = m.target_date
                WHERE m.status IN ('DONE', 'FAILED')
                  AND EXISTS (SELECT 1 FROM task_list_item live WHERE live.id = m.item_id)
            )
            UPDATE task_list_item tli
            SET parent_id = moved.target_list_id,
                position  = moved.new_position
            FROM moved
            WHERE tli.id = moved.item_id
            SQL);

        $this->write(sprintf('Moved %d completed task(s) to the right day\'s list.', $moved));
    }

    /**
     * Anything still to do returns to the unassigned pool of its own day.
     */
    private function repairPending(): void
    {
        $unassigned = $this->connection->executeStatement(<<<'SQL'
            UPDATE task
            SET assigned_to = NULL
            WHERE id IN (SELECT task_id FROM misfiled WHERE status NOT IN ('DONE', 'FAILED'))
            SQL);

        $removed = $this->connection->executeStatement(<<<'SQL'
            DELETE FROM task_list_item
            WHERE id IN (SELECT item_id FROM misfiled WHERE status NOT IN ('DONE', 'FAILED'))
            SQL);

        $this->write(sprintf(
            'Unassigned %d task(s) still to do and removed their %d task list item(s).',
            $unassigned,
            $removed
        ));
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException(
            'Repaired assignments cannot be put back on the wrong day.'
        );
    }
}
