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
        // All statements are idempotent: they only add keys that are missing, so
        // re-running (e.g. after a partial apply) never overwrites anything.
        $this->addSql('ALTER TABLE delivery ADD COLUMN IF NOT EXISTS metadata JSON DEFAULT NULL');

        // 1. RDC deliveries: move provenance (rdc.lo_uri, rdc.created_at) from
        //    the pickup task metadata into the bag. Merges into whatever is
        //    already there, and skips deliveries that have an 'rdc' key.
        $this->addSql(
            "UPDATE delivery d
             SET metadata = COALESCE(d.metadata::jsonb, '{}'::jsonb)
                           || jsonb_build_object('rdc', jsonb_build_object(
                               'lo_uri', t.metadata->>'rdc_lo_uri',
                               'created_at', t.metadata->>'rdc_created_at'
                           ))
             FROM task_collection_item tci
             JOIN task t ON tci.task_id = t.id
             WHERE tci.parent_id = d.id
               AND t.type = 'PICKUP'
               AND t.metadata->>'rdc_lo_uri' IS NOT NULL
               AND d.metadata->>'rdc' IS NULL"
        );

        // 2. RDC deliveries: add the external reference (REQUESTOR_ID, stored
        //    flat as rdc_external_ref on the same pickup task). Only added when
        //    the delivery has no external reference yet.
        $this->addSql(
            "UPDATE delivery d
             SET metadata = COALESCE(d.metadata::jsonb, '{}'::jsonb)
                           || jsonb_build_object('external_reference', t.metadata->>'rdc_external_ref')
             FROM task_collection_item tci
             JOIN task t ON tci.task_id = t.id
             WHERE tci.parent_id = d.id
               AND t.type = 'PICKUP'
               AND t.metadata->>'rdc_external_ref' IS NOT NULL
               AND d.metadata->>'external_reference' IS NULL"
        );

        // 3. Transporter deliveries: SyncTransportersCommand uses the point id
        //    both as the delivery external reference and as the pickup task
        //    metadata barcode; deliveries imported before that only have it
        //    there. A delivery is never both RDC and transporter, and RDC
        //    pickups always carry rdc_lo_uri, so filtering on it is enough.
        //    A delivery can have several pickups, so DISTINCT ON keeps a single
        //    barcode per delivery (a plain LIMIT 1 would only pick one row
        //    globally).
        $this->addSql(
            "UPDATE delivery d
             SET metadata = COALESCE(d.metadata::jsonb, '{}'::jsonb)
                           || jsonb_build_object('external_reference', p.barcode)
             FROM (
                 SELECT DISTINCT ON (tci.parent_id) tci.parent_id AS id, t.metadata->>'barcode' AS barcode
                 FROM task_collection_item tci
                 JOIN task t ON tci.task_id = t.id
                 WHERE t.type = 'PICKUP'
                   AND t.metadata->>'barcode' IS NOT NULL
                   AND t.metadata->>'barcode' <> ''
                   AND t.metadata->>'rdc_lo_uri' IS NULL
                 ORDER BY tci.parent_id, t.id
             ) p
             WHERE p.id = d.id
               AND d.metadata->>'external_reference' IS NULL"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE delivery DROP COLUMN metadata');
    }
}
