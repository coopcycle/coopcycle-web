<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Keep track of how far a delivery import has got.
 *
 * A large file takes hours to import, mostly spent geocoding addresses, and
 * the admin had no way to tell a slow import from a dead one.
 */
final class Version20261118160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add progress columns to delivery_import_queue';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE delivery_import_queue ADD total_rows INT DEFAULT NULL');
        $this->addSql('ALTER TABLE delivery_import_queue ADD processed_rows INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE delivery_import_queue ADD error_rows INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE delivery_import_queue ADD progressed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE delivery_import_queue DROP total_rows');
        $this->addSql('ALTER TABLE delivery_import_queue DROP processed_rows');
        $this->addSql('ALTER TABLE delivery_import_queue DROP error_rows');
        $this->addSql('ALTER TABLE delivery_import_queue DROP progressed_at');
    }
}
