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
        $this->addSql('ALTER TABLE store_time_slot DROP CONSTRAINT FK_5CE54537B092A811');
        $this->addSql('ALTER TABLE store_time_slot DROP CONSTRAINT store_time_slot_pkey');
        $this->addSql('ALTER TABLE store_time_slot ADD COLUMN id SERIAL NOT NULL');
        $this->addSql('ALTER TABLE store_time_slot ADD COLUMN position INT NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE store_time_slot ADD PRIMARY KEY (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX store_time_slot_pkey');
        $this->addSql('ALTER TABLE store_time_slot DROP COLUMN position, id');
        $this->addSql('ALTER TABLE store_time_slot ADD PRIMARY KEY (store_id, time_slot_id)');
    }
}
