<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200915100034 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    private function linkToRestaurants()
    {
        $stmt = $this->connection->prepare('SELECT t.id AS task_id, r.organization_id FROM sylius_order o JOIN delivery d ON d.order_id = o.id JOIN task t ON t.delivery_id = d.id JOIN restaurant r ON o.restaurant_id = r.id');
        $stmt->execute();
        while ($task = $stmt->fetch()) {
            $this->addSql('UPDATE task SET organization_id = :organization_id WHERE id = :task_id', $task);
        }
    }

    private function linkToStores()
    {
        $stmt = $this->connection->prepare('SELECT t.id AS task_id, s.organization_id FROM delivery d JOIN task t ON t.delivery_id = d.id JOIN store s ON d.store_id = s.id');
        $stmt->execute();
        while ($task = $stmt->fetch()) {
            $this->addSql('UPDATE task SET organization_id = :organization_id WHERE id = :task_id', $task);
        }
    }

    public function up(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE task ADD organization_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE task ADD CONSTRAINT FK_527EDB2532C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_527EDB2532C8A3DE ON task (organization_id)');

        $this->linkToRestaurants();
        $this->linkToStores();
    }

    public function down(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('UPDATE task SET organization_id = NULL');

        $this->addSql('ALTER TABLE task DROP CONSTRAINT FK_527EDB2532C8A3DE');
        $this->addSql('DROP INDEX IDX_527EDB2532C8A3DE');
        $this->addSql('ALTER TABLE task DROP organization_id');
    }
}
