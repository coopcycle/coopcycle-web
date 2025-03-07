<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230807105004 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tour ADD date DATE');
        $this->addSql('CREATE INDEX IDX_6AD1F969AA9E377A ON tour (date)');

        $tasksStmt = $this->connection->prepare('SELECT t.id, t.done_after FROM task t JOIN task_collection_item i ON i.task_id = t.id WHERE i.parent_id = :tour_id');

        $stmt = $this->connection->prepare('SELECT id FROM tour');

        $result = $stmt->execute();

        while ($tour = $result->fetchAssociative()) {

            $tasksStmt->bindParam('tour_id', $tour['id']);
            $result2 = $tasksStmt->execute();

            $dates = [];
            while ($task = $result2->fetchAssociative()) {
                $dates[] = new \DateTime($task['done_after']);
            }

            $date = array_reduce($dates, function (\DateTime $carry, \DateTime $date) {
                if ($date < $carry) {
                    return $date;
                }

                return $date;
            }, new \DateTime());

            $this->addSql('UPDATE tour SET date = :date WHERE id = :id', [
                'date' => $date->format('Y-m-d'),
                'id' => $tour['id'],
            ]);
        }

        $this->addSql('ALTER TABLE tour ALTER COLUMN date SET NOT NULL');

    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tour DROP date');
    }
}
