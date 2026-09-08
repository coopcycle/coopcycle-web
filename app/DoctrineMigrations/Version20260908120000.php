<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260908120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add export_watermark (incremental analytics export state, one row per export type)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE export_watermark (
              type VARCHAR(32) NOT NULL,
              watermark_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              last_run_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              last_run_rows INT DEFAULT 0 NOT NULL,
              last_run_overlap_rows INT DEFAULT 0 NOT NULL,
              PRIMARY KEY(type)
            )
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE export_watermark');
    }
}
