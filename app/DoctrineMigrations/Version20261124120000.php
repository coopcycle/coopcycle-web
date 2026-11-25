<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Keep the mapping of a file's columns to the fields they hold.
 *
 * Written when a dispatcher confirms it on the mapping screen, and read back when a
 * worker imports the file. Null for the imports that predate the mapping screen and
 * for the files sent to the API, which are read by their header names.
 *
 * The status column already holds the new "mapping" status, being long enough.
 */
final class Version20261124120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the column mapping to delivery_import_queue';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE delivery_import_queue ADD column_mapping JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE delivery_import_queue DROP column_mapping');
    }
}
