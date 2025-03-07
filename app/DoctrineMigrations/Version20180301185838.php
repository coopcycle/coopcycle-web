<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180301185838 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE task ADD assigned_to INT DEFAULT NULL');
        $this->addSql('ALTER TABLE task ADD CONSTRAINT FK_527EDB2589EEAF91 FOREIGN KEY (assigned_to) REFERENCES api_user (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_527EDB2589EEAF91 ON task (assigned_to)');

        $stmt = $this->connection->prepare('SELECT task_id, courier_id FROM task_assignment WHERE courier_id IS NOT NULL');
        $result = $stmt->execute();
        while ($assignment = $result->fetchAssociative()) {
            $this->addSql('UPDATE task SET assigned_to = :courier_id WHERE id = :task_id', $assignment);
        }

        $this->addSql('DROP TABLE task_assignment');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE task_assignment (task_id INT NOT NULL, courier_id INT NOT NULL, "position" INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(task_id, courier_id))');
        $this->addSql('CREATE INDEX idx_2cd60f15e3d8151c ON task_assignment (courier_id)');
        $this->addSql('CREATE INDEX idx_2cd60f158db60186 ON task_assignment (task_id)');
        $this->addSql('ALTER TABLE task_assignment ADD CONSTRAINT fk_2cd60f15e3d8151c FOREIGN KEY (courier_id) REFERENCES api_user (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE task_assignment ADD CONSTRAINT fk_2cd60f158db60186 FOREIGN KEY (task_id) REFERENCES task (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

        $stmt = $this->connection->prepare('SELECT t.id AS task_id, t.assigned_to AS courier_id, i.position FROM task t JOIN task_collection_item i ON t.id = i.task_id JOIN task_collection c ON i.parent_id = c.id WHERE c.type = \'task_list\' AND t.assigned_to IS NOT NULL');

        $result = $stmt->execute();
        while ($taskListItem = $result->fetchAssociative()) {
            $this->addSql('INSERT INTO task_assignment (task_id, courier_id, "position", created_at, updated_at) VALUES (:task_id,  :courier_id, :position, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)', [
                $taskListItem['task_id'],
                $taskListItem['courier_id'],
                $taskListItem['position'],
            ]);
        }

        $this->addSql('ALTER TABLE task DROP CONSTRAINT FK_527EDB2589EEAF91');
        $this->addSql('DROP INDEX IDX_527EDB2589EEAF91');
        $this->addSql('ALTER TABLE task DROP assigned_to');
    }
}
