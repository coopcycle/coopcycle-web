<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230920192807 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE notification (id SERIAL NOT NULL, name VARCHAR(64) NOT NULL, enabled BOOLEAN DEFAULT \'true\' NOT NULL, PRIMARY KEY(id))');

        $events = [
            'order:created',
            'order:accepted',
            'order:refused',
            'order:delayed',
            'order:cancelled',
            'order:picked',
            'order:dropped',
            'order:fulfilled',
            'order:updated',
            'task:created',
            'task:assigned',
            'task:unassigned',
            'task:started',
            'task:done',
            'task:failed',
            'task:cancelled',
            'task_collections:updated',
            'task_list:updated',
            'task:rescheduled',
            'task_import:success',
            'task_import:failure'
        ];

        foreach($events as $event) {
            $this->addSql('INSERT INTO notification (name) VALUES (:name)', [
                'name' => $event
            ]);
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE notification');
    }
}
