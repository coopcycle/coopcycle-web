<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Keep track of the deliveries of an import being cancelled by a dispatcher.
 *
 * They are found by the import id in their metadata: the index keeps that from
 * reading the whole delivery table, for an import or for the list of imports.
 */
final class Version20261123100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add deliveries cancellation columns to delivery_import_queue, index deliveries by import';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE delivery_import_queue ADD deliveries_cancellation VARCHAR(16) DEFAULT NULL');
        $this->addSql('ALTER TABLE delivery_import_queue ADD deliveries_to_cancel INT DEFAULT NULL');
        $this->addSql('ALTER TABLE delivery_import_queue ADD cancelled_deliveries INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE delivery_import_queue ADD skipped_deliveries INT DEFAULT 0 NOT NULL');
        $this->addSql("CREATE INDEX idx_delivery_import_id ON delivery ((metadata->>'delivery_import_id')) WHERE metadata->>'delivery_import_id' IS NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_delivery_import_id');
        $this->addSql('ALTER TABLE delivery_import_queue DROP deliveries_cancellation');
        $this->addSql('ALTER TABLE delivery_import_queue DROP deliveries_to_cancel');
        $this->addSql('ALTER TABLE delivery_import_queue DROP cancelled_deliveries');
        $this->addSql('ALTER TABLE delivery_import_queue DROP skipped_deliveries');
    }
}
