<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20191121130720 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        $stmt = $this->connection->prepare('SELECT id, done_after, done_before FROM task WHERE done_after > done_before');
        $stmt->execute();

        while ($task = $stmt->fetch()) {
            $this->addSql('UPDATE task SET done_after = :after, done_before = :before WHERE id = :id', [
                'after' => $task['done_before'],
                'before' => $task['done_after'],
                'id' => $task['id'],
            ]);
        }

        $stmt = $this->connection->prepare('SELECT id, done_after, done_before FROM task WHERE done_after = done_before');
        $stmt->execute();

        while ($task = $stmt->fetch()) {

            $after = new \DateTime($task['done_after']);
            $after->modify('-5 minutes');

            $this->addSql('UPDATE task SET done_after = :after WHERE id = :id', [
                'after' => $after->format('Y-m-d H:i:s'),
                'id' => $task['id'],
            ]);
        }
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
    }
}
