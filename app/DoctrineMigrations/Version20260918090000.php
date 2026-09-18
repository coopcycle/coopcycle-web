<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260918090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Mirror delivery.metadata->external_reference into the metadata of every task of the delivery';
    }

    public function up(Schema $schema): void
    {
        // `Delivery::mirrorExternalReferenceTo()` keeps the two bags in sync
        // from now on; this backfills the deliveries imported before it.
        // Idempotent: it only writes tasks that don't carry the key yet, so a
        // re-run never overwrites a reference edited on the task itself.
        $this->addSql(
            "UPDATE task t
             SET metadata = COALESCE(t.metadata::jsonb, '{}'::jsonb)
                           || jsonb_build_object('external_reference', d.metadata->>'external_reference')
             FROM task_collection_item tci
             JOIN delivery d ON tci.parent_id = d.id
             WHERE tci.task_id = t.id
               AND d.metadata->>'external_reference' IS NOT NULL
               AND t.metadata->>'external_reference' IS NULL"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE task SET metadata = metadata::jsonb - 'external_reference'");
    }
}
