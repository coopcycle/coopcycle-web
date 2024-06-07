<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180312085302 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE task_collection ADD distance INT DEFAULT NULL');
        $this->addSql('ALTER TABLE task_collection ADD duration INT DEFAULT NULL');
        $this->addSql('ALTER TABLE task_collection ADD polyline TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE task_collection ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE task_collection ADD updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');

        $stmt = $this->connection->prepare('SELECT id, distance, duration, polyline, created_at, updated_at FROM task_list');
        $result = $stmt->execute();
        while ($taskList = $result->fetchAssociative()) {
            $this->addSql('UPDATE task_collection SET distance = :distance, duration = :duration, polyline = :polyline, created_at = :created_at, updated_at = :updated_at WHERE id = :id', $taskList);
        }

        $stmt = $this->connection->prepare('SELECT id, distance, duration, polyline FROM delivery');
        $result = $stmt->execute();
        while ($delivery = $result->fetchAssociative()) {
            $this->addSql('UPDATE task_collection SET distance = :distance, duration = :duration, polyline = :polyline WHERE id = :id', $delivery);
        }

        $this->addSql('UPDATE task_collection SET distance = 0 WHERE distance IS NULL');
        $this->addSql('UPDATE task_collection SET duration = 0 WHERE duration IS NULL');
        $this->addSql('UPDATE task_collection SET polyline = \'\' WHERE polyline IS NULL');
        $this->addSql('UPDATE task_collection SET created_at = CURRENT_TIMESTAMP WHERE created_at IS NULL');
        $this->addSql('UPDATE task_collection SET updated_at = CURRENT_TIMESTAMP WHERE updated_at IS NULL');

        $this->addSql('ALTER TABLE task_collection ALTER COLUMN distance SET NOT NULL');
        $this->addSql('ALTER TABLE task_collection ALTER COLUMN duration SET NOT NULL');
        $this->addSql('ALTER TABLE task_collection ALTER COLUMN polyline SET NOT NULL');
        $this->addSql('ALTER TABLE task_collection ALTER COLUMN created_at SET NOT NULL');
        $this->addSql('ALTER TABLE task_collection ALTER COLUMN updated_at SET NOT NULL');

        $this->addSql('ALTER TABLE delivery DROP distance');
        $this->addSql('ALTER TABLE delivery DROP duration');
        $this->addSql('ALTER TABLE delivery DROP polyline');

        $this->addSql('ALTER TABLE task_list DROP duration');
        $this->addSql('ALTER TABLE task_list DROP distance');
        $this->addSql('ALTER TABLE task_list DROP polyline');
        $this->addSql('ALTER TABLE task_list DROP created_at');
        $this->addSql('ALTER TABLE task_list DROP updated_at');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE delivery ADD distance INT DEFAULT NULL');
        $this->addSql('ALTER TABLE delivery ADD duration INT DEFAULT NULL');
        $this->addSql('ALTER TABLE delivery ADD polyline TEXT DEFAULT NULL');

        $this->addSql('ALTER TABLE task_list ADD duration INT DEFAULT NULL');
        $this->addSql('ALTER TABLE task_list ADD distance INT DEFAULT NULL');
        $this->addSql('ALTER TABLE task_list ADD polyline TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE task_list ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE task_list ADD updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');

        $stmt = $this->connection->prepare('SELECT id, distance, duration, polyline, created_at, updated_at FROM task_collection');
        $result = $stmt->execute();
        while ($taskCollection = $result->fetchAssociative()) {
            $this->addSql('UPDATE delivery SET distance = :distance, duration = :duration, polyline = :polyline WHERE id = :id', $taskCollection);
            $this->addSql('UPDATE task_list SET distance = :distance, duration = :duration, polyline = :polyline, created_at = :created_at, updated_at = :updated_at WHERE id = :id', $taskCollection);
        }

        $this->addSql('ALTER TABLE delivery ALTER COLUMN distance SET NOT NULL');
        $this->addSql('ALTER TABLE delivery ALTER COLUMN duration SET NOT NULL');
        $this->addSql('ALTER TABLE delivery ALTER COLUMN polyline SET NOT NULL');

        $this->addSql('ALTER TABLE task_list ALTER COLUMN distance SET NOT NULL');
        $this->addSql('ALTER TABLE task_list ALTER COLUMN duration SET NOT NULL');
        $this->addSql('ALTER TABLE task_list ALTER COLUMN polyline SET NOT NULL');
        $this->addSql('ALTER TABLE task_list ALTER COLUMN created_at SET NOT NULL');
        $this->addSql('ALTER TABLE task_list ALTER COLUMN updated_at SET NOT NULL');

        $this->addSql('ALTER TABLE task_collection DROP distance');
        $this->addSql('ALTER TABLE task_collection DROP duration');
        $this->addSql('ALTER TABLE task_collection DROP polyline');
        $this->addSql('ALTER TABLE task_collection DROP created_at');
        $this->addSql('ALTER TABLE task_collection DROP updated_at');
    }
}
