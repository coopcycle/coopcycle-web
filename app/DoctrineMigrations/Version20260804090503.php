<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Third data migration for the #874 / #5249 unassignment bug.
 *
 * The first repair migration (Version20260804081026) had no tour guard, so for
 * tasks that belong to a tour it wrongly (a) set `assigned_to` to a stale
 * individual courier and (b) created a standalone `task_list_item`. A task in a
 * tour must be listed *only* through its tour, so those tasks ended up
 * double-listed, and their `assigned_to` no longer matched the tour's courier.
 *
 * This migration repairs a task that:
 *   - belongs to a tour that is itself listed in a task list, and
 *   - also carries a direct (standalone) task_list_item,
 * by:
 *   - realigning `assigned_to` to the tour's courier (source of truth), and
 *   - deleting the stray direct list item(s), so the task is listed only via
 *     its tour.
 *
 * Tours whose listing is missing are left untouched (deleting the direct item
 * would make the task disappear). Idempotent and safe to re-run.
 */
final class Version20260804090503 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Un-double-list tour tasks wrongly given a direct list item by the first repair migration (#874 / #5249).';
    }

    public function up(Schema $schema): void
    {
        // 1. Tasks that are in a *listed* tour yet also carry a direct list item.
        //    tour.id maps to task_collection.id (joined inheritance); a tour is
        //    listed through a task_list_item whose tour_id points at it.
        $this->addSql(<<<'SQL'
            CREATE TEMPORARY TABLE _bug874_tourdup AS
            SELECT DISTINCT
                t.id            AS task_id,
                ttl.courier_id  AS tour_courier_id
            FROM task t
            JOIN task_collection_item tci ON tci.task_id = t.id
            JOIN task_collection tc ON tc.id = tci.parent_id AND tc.type = 'tour'
            JOIN tour tr ON tr.id = tc.id
            JOIN task_list_item tli ON tli.tour_id = tr.id        -- the tour is listed
            JOIN task_list ttl ON ttl.id = tli.parent_id
            WHERE EXISTS (
                SELECT 1 FROM task_list_item dli
                WHERE dli.task_id = t.id AND dli.parent_id IS NOT NULL
            )
        SQL);

        // 2. Realign the task to the courier that actually runs the tour.
        $this->addSql(<<<'SQL'
            UPDATE task t
            SET assigned_to = d.tour_courier_id
            FROM _bug874_tourdup d
            WHERE t.id = d.task_id
              AND t.assigned_to IS DISTINCT FROM d.tour_courier_id
        SQL);

        // 3. Remove the stray direct list item(s) (both listed and orphaned);
        //    the tour's own item (task_id IS NULL) is not matched here.
        $this->addSql(<<<'SQL'
            DELETE FROM task_list_item dli
            USING _bug874_tourdup d
            WHERE dli.task_id = d.task_id
        SQL);

        $this->addSql('DROP TABLE _bug874_tourdup');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException(
            'Data repair of double-listed tour tasks cannot be reversed.'
        );
    }
}
