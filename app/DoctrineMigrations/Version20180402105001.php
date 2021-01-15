<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180402105001 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $stmt = $this->connection->prepare('SELECT order_id, delivery_id FROM delivery_order_item JOIN sylius_order_item ON delivery_order_item.order_item_id = sylius_order_item.id');
        $stmt->execute();
        while ($row = $stmt->fetch()) {
            $this->addSql('UPDATE delivery SET sylius_order_id = :order_id WHERE id = :delivery_id', $row);
        }

        $this->addSql('DROP TABLE delivery_order_item');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE delivery_order_item (order_item_id INT NOT NULL, delivery_id INT NOT NULL, PRIMARY KEY(order_item_id, delivery_id))');
        $this->addSql('CREATE INDEX idx_4caf882d12136921 ON delivery_order_item (delivery_id)');
        $this->addSql('CREATE INDEX idx_4caf882de415fb15 ON delivery_order_item (order_item_id)');
        $this->addSql('ALTER TABLE delivery_order_item ADD CONSTRAINT fk_4caf882d12136921 FOREIGN KEY (delivery_id) REFERENCES delivery (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE delivery_order_item ADD CONSTRAINT fk_4caf882de415fb15 FOREIGN KEY (order_item_id) REFERENCES sylius_order_item (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
