<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Lets a recurrence rule be prepared in advance, or expire: orders are only
 * generated between these two days, both included.
 */
final class Version20261013110751 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add start_date & end_date columns on task_rrule';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE task_rrule ADD start_date DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE task_rrule ADD end_date DATE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE task_rrule DROP start_date');
        $this->addSql('ALTER TABLE task_rrule DROP end_date');
    }
}
