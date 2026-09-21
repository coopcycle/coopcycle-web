<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260921152858 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add task_rrule_generation (state of the recurring order generation, one row per date)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE task_rrule_generation (id SERIAL NOT NULL, date DATE NOT NULL, status VARCHAR(16) NOT NULL, succeeded INT NOT NULL, failed INT NOT NULL, attempts INT NOT NULL, errors JSON NOT NULL, started_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, finished_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX task_rrule_generation_date_uniq ON task_rrule_generation (date)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE task_rrule_generation');
    }
}
