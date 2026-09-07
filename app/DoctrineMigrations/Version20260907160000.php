<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260907160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add delivery.metadata JSON column; backfill it from pickup task metadata (RDC provenance, external reference)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE delivery ADD metadata JSON DEFAULT NULL');

        // 1. RDC deliveries: provenance used to live on the pickup task metadata
        //    (flat rdc_lo_uri / rdc_created_at keys). Move it into the bag.
        $this->addSql(
            "UPDATE delivery d
             SET metadata = jsonb_build_object('rdc', jsonb_build_object(
                 'lo_uri', t.metadata->>'rdc_lo_uri',
                 'created_at', t.metadata->>'rdc_created_at'
             ))
             FROM task_collection_item tci
             JOIN task t ON tci.task_id = t.id
             WHERE tci.parent = d.id
               AND t.type = 'PICKUP'
               AND t.metadata->>'rdc_lo_uri' IS NOT NULL"
        );

        // 2. RDC deliveries: the external reference (REQUESTOR_ID) used to be
        //    stored flat as rdc_external_ref on the same pickup task.
        $this->addSql(
            "UPDATE delivery d
             SET metadata = d.metadata::jsonb || jsonb_build_object(
                 'external_reference', t.metadata->>'rdc_external_ref'
             )
             FROM task_collection_item tci
             JOIN task t ON tci.task_id = t.id
             WHERE tci.parent = d.id
               AND t.type = 'PICKUP'
               AND t.metadata->>'rdc_external_ref' IS NOT NULL"
        );

        // 3. Transporter deliveries: SyncTransportersCommand sets the delivery
        //    external reference to the transporter point id, which
        //    ImportFromPoint also stores as the pickup task barcode. Deliveries
        //    imported before that only have it there. A delivery is never both
        //    RDC and transporter, so filtering out rows with rdc_lo_uri (which
        //    every RDC import sets) is enough.
        $this->addSql(
            "UPDATE delivery d
             SET metadata = jsonb_build_object('external_reference', t.metadata->>'barcode')
             FROM task_collection_item tci
             JOIN task t ON tci.task_id = t.id
             WHERE tci.parent = d.id
               AND t.type = 'PICKUP'
               AND t.metadata->>'barcode' IS NOT NULL
               AND t.metadata->>'rdc_lo_uri' IS NULL
               AND d.metadata IS NULL"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE delivery DROP metadata');
    }
}
