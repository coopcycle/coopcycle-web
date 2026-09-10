<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Unlink time slots still referenced by soft-deleted stores.
 *
 * PostSoftDeleteSubscriber did not clear Store::$timeSlot / Store::$timeSlots
 * when a store was soft-deleted, so `store.time_slot_id` and `store_time_slot`
 * rows kept pointing at the time slot. Since the store row is never actually
 * removed (soft delete), those references survive forever and the time slot
 * can never be hard-deleted (FK constraint), even though the admin UI reports
 * it as still "applied to" a store that no longer effectively exists.
 *
 * NB: this is a data migration and runs its statements directly, so it
 * reports real row counts — but `--dry-run` will not hold it back.
 */
final class Version20260910100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Unlink time slots referenced by soft-deleted stores (executes immediately, not dry-run safe)';
    }

    public function up(Schema $schema): void
    {
        $unlinked = $this->connection->executeStatement(<<<'SQL'
            UPDATE store
            SET time_slot_id = NULL
            WHERE deleted_at IS NOT NULL
              AND time_slot_id IS NOT NULL
            SQL);

        $this->write(sprintf('Unlinked %d soft-deleted store(s) from store.time_slot_id.', $unlinked));

        $removed = $this->connection->executeStatement(<<<'SQL'
            DELETE FROM store_time_slot
            WHERE store_id IN (SELECT id FROM store WHERE deleted_at IS NOT NULL)
            SQL);

        $this->write(sprintf('Removed %d store_time_slot row(s) belonging to soft-deleted stores.', $removed));
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException(
            'Unlinked time slot references on soft-deleted stores cannot be restored.'
        );
    }
}
