<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180225152719 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE task_collection (id SERIAL NOT NULL, type VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE task_collection_item (id SERIAL NOT NULL, parent_id INT DEFAULT NULL, task_id INT DEFAULT NULL, position INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_13199EFF727ACA70 ON task_collection_item (parent_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_13199EFF8DB60186 ON task_collection_item (task_id)');
        $this->addSql('ALTER TABLE task_collection_item ADD CONSTRAINT FK_13199EFF727ACA70 FOREIGN KEY (parent_id) REFERENCES task_collection (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE task_collection_item ADD CONSTRAINT FK_13199EFF8DB60186 FOREIGN KEY (task_id) REFERENCES task (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

        $tasksByDelivery = [];

        $stmt = $this->connection->prepare('SELECT * FROM task WHERE delivery_id IS NOT NULL');

        $stmt->execute();
        while ($task = $stmt->fetch()) {
            $tasksByDelivery[$task['delivery_id']][] = $task;
        }

        foreach ($tasksByDelivery as $id => $tasks) {
            $this->addSql('INSERT INTO task_collection (id, type) VALUES (:delivery_id, :type)', [
                'delivery_id' => $id,
                'type' => 'delivery'
            ]);
            foreach ($tasks as $index => $task) {
                $this->addSql('INSERT INTO task_collection_item (parent_id, task_id, position) VALUES (:parent_id, :task_id, :position)', [
                    'parent_id' => $id,
                    'task_id' => $task['id'],
                    'position' => $index
                ]);
            }
        }
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE task_collection_item DROP CONSTRAINT FK_13199EFF727ACA70');
        $this->addSql('DROP TABLE task_collection');
        $this->addSql('DROP TABLE task_collection_item');
    }
}
