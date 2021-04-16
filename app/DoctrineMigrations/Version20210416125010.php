<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210416125010 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        $this->addSql('ALTER TABLE task ADD image_count INT DEFAULT 0 NOT NULL');

        $stmt = $this->connection->prepare('SELECT t.id, COUNT(*) AS image_count FROM task_image i JOIN task t ON t.id = i.task_id GROUP BY t.id');
        $stmt->execute();

        while ($task = $stmt->fetch()) {
            $this->addSql('UPDATE task SET image_count = :image_count WHERE id = :id', $task);
        }
    }

    public function down(Schema $schema) : void
    {
        $this->addSql('ALTER TABLE task DROP image_count');
    }
}
