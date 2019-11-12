<?php

namespace Application\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;
use Ramsey\Uuid\Uuid;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20171009155642 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE order_ ADD uuid VARCHAR(255) DEFAULT NULL');

        $stmt = $this->connection->prepare("SELECT * FROM order_");
        $stmt->execute();

        while ($order = $stmt->fetch()) {
            $this->addSql("UPDATE order_ SET uuid = :uuid WHERE id = :id", [
                'id' => $order['id'],
                'uuid' => Uuid::uuid4()->toString(),
            ]);
        }

        $this->addSql('ALTER TABLE order_ ALTER COLUMN uuid SET NOT NULL');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');
        $this->addSql('ALTER TABLE order_ DROP uuid');
    }
}
