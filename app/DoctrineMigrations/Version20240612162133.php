<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240612162133 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add position fieds to pivot table between Store and Timeslot';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE store_time_slot DROP CONSTRAINT store_time_slot_pkey');
        $this->addSql('ALTER TABLE store_time_slot ADD COLUMN id SERIAL');
        $this->addSql('ALTER TABLE store_time_slot ADD COLUMN position INTEGER NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE store_time_slot DROP COLUMN position, id');
    }
}
