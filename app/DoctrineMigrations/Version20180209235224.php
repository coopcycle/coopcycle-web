<?php declare(strict_types = 1);

namespace Application\Migrations;

use AppBundle\Entity\Task;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180209235224 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE task ADD previous_task_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE task ADD CONSTRAINT FK_527EDB25BC2D6B55 FOREIGN KEY (previous_task_id) REFERENCES task (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_527EDB25BC2D6B55 ON task (previous_task_id)');

        $tasks = [];

        $stmt = $this->connection->prepare('SELECT * FROM task WHERE delivery_id IS NOT NULL');

        $result = $stmt->execute();
        while ($task = $result->fetchAssociative()) {
            $tasks[$task['delivery_id']][$task['type']] = $task;
        }

        foreach ($tasks as $deliveryId => $tasks) {
            $this->addSql('UPDATE task SET previous_task_id = :previous_task_id WHERE id = :id', [
                'previous_task_id' => $tasks[Task::TYPE_PICKUP]['id'],
                'id' => $tasks[Task::TYPE_DROPOFF]['id']
            ]);
        }
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE task DROP CONSTRAINT FK_527EDB25BC2D6B55');
        $this->addSql('DROP INDEX UNIQ_527EDB25BC2D6B55');
        $this->addSql('ALTER TABLE task DROP previous_task_id');
    }
}
