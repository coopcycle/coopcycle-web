<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180225183656 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE order_ ADD ready_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');

        $stmt = $this->connection->prepare('SELECT o.id AS order_id, d.duration, d.date FROM order_ o JOIN delivery d ON o.id = d.order_id');

        $result = $stmt->execute();
        while ($row = $result->fetchAssociative()) {

            $duration = $row['duration'];

            $readyAt = new \DateTime($row['date']);
            $readyAt->modify(sprintf('-%d seconds', $row['duration']));

            $this->addSql('UPDATE order_ SET ready_at = :ready_at WHERE id = :order_id', [
                'ready_at' => $readyAt->format('Y-m-d H:i:s'),
                'order_id' => $row['order_id']
            ]);
        }

        $this->addSql('UPDATE order_ SET ready_at = CURRENT_TIMESTAMP WHERE ready_at IS NULL');
        $this->addSql('ALTER TABLE order_ ALTER ready_at SET NOT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE order_ DROP ready_at');
    }
}
