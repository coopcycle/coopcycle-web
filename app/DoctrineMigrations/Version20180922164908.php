<?php declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20180922164908 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE task ADD next_task_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE task ADD CONSTRAINT FK_527EDB254F382B24 FOREIGN KEY (next_task_id) REFERENCES task (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_527EDB254F382B24 ON task (next_task_id)');

        $stmt = $this->connection->prepare('SELECT * FROM task WHERE previous_task_id IS NOT NULL');

        $result = $stmt->execute();
        while ($task = $result->fetchAssociative()) {
            $this->addSql('UPDATE task SET next_task_id = :next_task_id WHERE id = :id', [
                'next_task_id' => $task['id'],
                'id' => $task['previous_task_id']
            ]);
        }
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE task DROP CONSTRAINT FK_527EDB254F382B24');
        $this->addSql('DROP INDEX UNIQ_527EDB254F382B24');
        $this->addSql('ALTER TABLE task DROP next_task_id');
    }
}
