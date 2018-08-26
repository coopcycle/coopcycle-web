<?php declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20180826142926 extends AbstractMigration
{
    private $mapping = [
        'CREATE' => 'task:created',
        'ASSIGN' => 'task:assigned',
        'UNASSIGN' => 'task:unassigned',
        'DONE' => 'task:done',
        'FAILED' => 'task:failed',
    ];

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE task_event ADD data JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE task_event ADD metadata JSON DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN task_event.data IS \'(DC2Type:json_array)\'');
        $this->addSql('COMMENT ON COLUMN task_event.metadata IS \'(DC2Type:json_array)\'');

        $stmt = $this->connection->prepare('SELECT * FROM task_event');

        $stmt->execute();

        while ($taskEvent = $stmt->fetch()) {

            if (isset($this->mapping[$taskEvent['name']])) {
                $this->addSql('UPDATE task_event SET name = :name WHERE id = :id', [
                    'name' => $this->mapping[$taskEvent['name']],
                    'id' => $taskEvent['id'],
                ]);
            }

            $data = [];

            if (null !== $taskEvent['notes']) {
                $data['notes'] = $taskEvent['notes'];
            }

            $this->addSql('UPDATE task_event SET data = :data, metadata = :metadata WHERE id = :id', [
                'data' => json_encode($data),
                'metadata' => json_encode([]),
                'id' => $taskEvent['id'],
            ]);
        }

        $this->addSql('ALTER TABLE task_event ALTER data SET NOT NULL');
        $this->addSql('ALTER TABLE task_event ALTER metadata SET NOT NULL');

        $this->addSql('ALTER TABLE task_event DROP notes');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE task_event ADD notes TEXT DEFAULT NULL');

        $mapping = array_flip($this->mapping);

        $stmt = $this->connection->prepare('SELECT * FROM task_event');

        $stmt->execute();

        while ($taskEvent = $stmt->fetch()) {

            if (isset($mapping[$taskEvent['name']])) {
                $this->addSql('UPDATE task_event SET name = :name WHERE id = :id', [
                    'name' => $mapping[$taskEvent['name']],
                    'id' => $taskEvent['id'],
                ]);
            }

            $data = json_decode($taskEvent['data'], true);

            if (isset($data['notes'])) {
                $this->addSql('UPDATE task_event SET notes = :notes WHERE id = :id', [
                    'notes' => $data['notes'],
                    'id' => $taskEvent['id'],
                ]);
            }
        }

        $this->addSql('ALTER TABLE task_event DROP data');
        $this->addSql('ALTER TABLE task_event DROP metadata');
    }
}
