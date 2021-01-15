<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180226132824 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        // The same Task may belong to multiple TaskCollection
        $this->addSql('DROP INDEX UNIQ_13199EFF8DB60186');
        $this->addSql('CREATE UNIQUE INDEX task_collection_item_unique ON task_collection_item (parent_id, task_id)');

        $this->addSql('ALTER TABLE task_list DROP CONSTRAINT task_list_pkey');
        $this->addSql('ALTER TABLE task_list ADD id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE task_list ALTER date TYPE DATE');
        $this->addSql('ALTER TABLE task_list ALTER date DROP DEFAULT');
        $this->addSql('COMMENT ON COLUMN task_list.date IS NULL');
        $this->addSql('ALTER TABLE task_list ALTER duration TYPE INT');
        $this->addSql('ALTER TABLE task_list ALTER duration DROP DEFAULT');
        $this->addSql('ALTER TABLE task_list ALTER distance TYPE INT');
        $this->addSql('ALTER TABLE task_list ALTER distance DROP DEFAULT');

        $stmts = [];
        $stmts['assigned_tasks'] = $this->connection->prepare('SELECT t.id, DATE(t.done_before), ta.courier_id AS courier, ta.position FROM task t JOIN task_assignment ta ON t.id = ta.task_id');
        $stmts['task_list'] = $this->connection->prepare('SELECT * FROM task_list WHERE DATE(date) = :date AND courier_id = :courier');

        $taskLists = [];

        $stmts['assigned_tasks']->execute();
        while ($task = $stmts['assigned_tasks']->fetch()) {
            $taskLists[$task['date']][$task['courier']][] = $task;
        }

        foreach ($taskLists as $date => $tasksByCourier) {
            foreach ($tasksByCourier as $courier => $tasks) {

                $this->addSql('INSERT INTO task_collection (type) VALUES (:type)', [
                    'type' => 'task_list'
                ]);

                foreach ($tasks as $task) {
                    $this->addSql('INSERT INTO task_collection_item (parent_id, task_id, position) '
                        . 'SELECT MAX(id), :task_id, :position FROM task_collection WHERE type = :type', [
                        'type' => 'task_list',
                        'task_id' => $task['id'],
                        'position' => $task['position']
                    ]);
                }

                $stmts['task_list']->bindParam('date', $date);
                $stmts['task_list']->bindParam('courier', $courier);
                $stmts['task_list']->execute();

                $taskList = $stmts['task_list']->fetch();

                if ($taskList) {
                    $this->addSql('UPDATE task_list SET id = (SELECT MAX(id) FROM task_collection WHERE type = :type) '
                        . 'WHERE DATE(date) = :date AND courier_id = :courier', [
                        'type' => 'task_list',
                        'date' => $date,
                        'courier' => $courier
                    ]);
                } else {
                    $this->addSql('INSERT INTO task_list (id, date, courier_id, duration, distance, polyline, created_at, updated_at) SELECT MAX(id), :date, :courier, 0, 0, \'\', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP FROM task_collection WHERE type = :type', [
                        'date' => $date,
                        'courier' => $courier,
                        'type' => 'task_list',
                    ]);
                }

            }
        }

        // Make sure there are no "orphan" TaskList
        $this->addSql('DELETE FROM task_list WHERE id IS NULL');

        $this->addSql('ALTER TABLE task_list ALTER COLUMN id SET NOT NULL');

        $this->addSql('ALTER TABLE task_list ADD CONSTRAINT FK_377B6C63BF396750 FOREIGN KEY (id) REFERENCES task_collection (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX task_list_unique ON task_list (date, courier_id)');
        $this->addSql('ALTER TABLE task_list ADD PRIMARY KEY (id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP INDEX task_collection_item_unique');
        $this->addSql('ALTER TABLE task_list DROP CONSTRAINT FK_377B6C63BF396750');
        $this->addSql('DELETE FROM task_collection_item where parent_id IN (SELECT id FROM task_collection WHERE type = :type)', [
            'type' => 'task_list'
        ]);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_13199EFF8DB60186 ON task_collection_item (task_id)');

        $this->addSql('DROP INDEX task_list_unique');
        $this->addSql('ALTER TABLE task_list DROP id');
        $this->addSql('ALTER TABLE task_list ALTER courier_id SET NOT NULL');
        $this->addSql('ALTER TABLE task_list ALTER date TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('ALTER TABLE task_list ALTER date DROP DEFAULT');
        $this->addSql('COMMENT ON COLUMN task_list.date IS \'(DC2Type:date_string)\'');
        $this->addSql('ALTER TABLE task_list ALTER distance TYPE DOUBLE PRECISION');
        $this->addSql('ALTER TABLE task_list ALTER distance DROP DEFAULT');
        $this->addSql('ALTER TABLE task_list ALTER duration TYPE DOUBLE PRECISION');
        $this->addSql('ALTER TABLE task_list ALTER duration DROP DEFAULT');
        $this->addSql('ALTER TABLE task_list ADD PRIMARY KEY (date, courier_id)');
    }
}
