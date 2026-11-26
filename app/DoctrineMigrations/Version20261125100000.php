<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * One queue for every kind of import, not only deliveries.
 *
 * The table keeps its rows: every import it holds is a delivery import, which is what
 * the new type column is backfilled with. The task imports to come are rows of the same
 * table, and the columns that counted what a cancellation had got through stop being
 * about deliveries.
 *
 * Postgres keeps the old names of the primary key, the sequence, the index on store_id
 * and its foreign key when a table is renamed, while Doctrine derives the names it
 * expects from the table name: they are renamed explicitly, to the names
 * `doctrine:schema:create --dump-sql` gives for the new table, so that a database
 * migrated from here and one created from the mapping are the same.
 *
 * The index on task.metadata->>'task_import_id' is the task counterpart of the one
 * Version20261123100000 created for deliveries: it is what finding the tasks an import
 * created, to cancel them, reads.
 */
final class Version20261125100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Turn delivery_import_queue into import_queue, for imports of any kind';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE delivery_import_queue RENAME TO import_queue');
        $this->addSql('ALTER SEQUENCE delivery_import_queue_id_seq RENAME TO import_queue_id_seq');
        $this->addSql('ALTER INDEX delivery_import_queue_pkey RENAME TO import_queue_pkey');
        $this->addSql('ALTER INDEX idx_d2b29a9b092a811 RENAME TO idx_92a8d0adb092a811');
        $this->addSql('ALTER TABLE import_queue RENAME CONSTRAINT fk_d2b29a9b092a811 TO fk_92a8d0adb092a811');

        // Added nullable and backfilled rather than NOT NULL with a default: there are rows,
        // and a default would let a bug write an import of no particular kind as a delivery one
        $this->addSql('ALTER TABLE import_queue ADD type VARCHAR(16) DEFAULT NULL');
        $this->addSql("UPDATE import_queue SET type = 'deliveries'");
        $this->addSql('ALTER TABLE import_queue ALTER type SET NOT NULL');

        $this->addSql('ALTER TABLE import_queue ADD options JSON DEFAULT NULL');

        $this->addSql('ALTER TABLE import_queue ADD organization_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE import_queue ADD CONSTRAINT FK_92A8D0AD32C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_92A8D0AD32C8A3DE ON import_queue (organization_id)');

        $this->addSql('ALTER TABLE import_queue RENAME COLUMN deliveries_cancellation TO items_cancellation');
        $this->addSql('ALTER TABLE import_queue RENAME COLUMN deliveries_to_cancel TO items_to_cancel');
        $this->addSql('ALTER TABLE import_queue RENAME COLUMN cancelled_deliveries TO cancelled_items');
        $this->addSql('ALTER TABLE import_queue RENAME COLUMN skipped_deliveries TO skipped_items');

        $this->addSql("CREATE INDEX idx_task_import_id ON task ((metadata->>'task_import_id')) WHERE metadata->>'task_import_id' IS NOT NULL");
    }

    /**
     * Lossy on purpose: the imports of any other kind have no home in the table this
     * goes back to, and nothing that reads it could make sense of them.
     */
    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_task_import_id');

        $this->addSql("DELETE FROM import_queue WHERE type <> 'deliveries'");

        $this->addSql('ALTER TABLE import_queue RENAME COLUMN items_cancellation TO deliveries_cancellation');
        $this->addSql('ALTER TABLE import_queue RENAME COLUMN items_to_cancel TO deliveries_to_cancel');
        $this->addSql('ALTER TABLE import_queue RENAME COLUMN cancelled_items TO cancelled_deliveries');
        $this->addSql('ALTER TABLE import_queue RENAME COLUMN skipped_items TO skipped_deliveries');

        $this->addSql('ALTER TABLE import_queue DROP CONSTRAINT FK_92A8D0AD32C8A3DE');
        $this->addSql('DROP INDEX IDX_92A8D0AD32C8A3DE');
        $this->addSql('ALTER TABLE import_queue DROP organization_id');

        $this->addSql('ALTER TABLE import_queue DROP options');
        $this->addSql('ALTER TABLE import_queue DROP type');

        $this->addSql('ALTER TABLE import_queue RENAME CONSTRAINT fk_92a8d0adb092a811 TO fk_d2b29a9b092a811');
        $this->addSql('ALTER INDEX idx_92a8d0adb092a811 RENAME TO idx_d2b29a9b092a811');
        $this->addSql('ALTER INDEX import_queue_pkey RENAME TO delivery_import_queue_pkey');
        $this->addSql('ALTER SEQUENCE import_queue_id_seq RENAME TO delivery_import_queue_id_seq');
        $this->addSql('ALTER TABLE import_queue RENAME TO delivery_import_queue');
    }
}
