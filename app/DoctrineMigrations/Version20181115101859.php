<?php declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20181115101859 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE delivery ADD store_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE delivery ADD CONSTRAINT FK_3781EC10B092A811 FOREIGN KEY (store_id) REFERENCES store (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_3781EC10B092A811 ON delivery (store_id)');

        $stmt = $this->connection->prepare('SELECT * FROM store_delivery');

        $result = $stmt->execute();
        while ($storeDelivery = $result->fetchAssociative()) {
            $this->addSql('UPDATE delivery SET store_id = :store_id WHERE id = :delivery_id', [
                'store_id' => $storeDelivery['store_id'],
                'delivery_id' => $storeDelivery['delivery_id'],
            ]);
        }

        $this->addSql('DROP TABLE store_delivery');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE store_delivery (store_id INT NOT NULL, delivery_id INT NOT NULL, PRIMARY KEY(store_id, delivery_id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_9f693ea12136921 ON store_delivery (delivery_id)');
        $this->addSql('CREATE INDEX idx_9f693eab092a811 ON store_delivery (store_id)');
        $this->addSql('ALTER TABLE store_delivery ADD CONSTRAINT fk_9f693ea12136921 FOREIGN KEY (delivery_id) REFERENCES delivery (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE store_delivery ADD CONSTRAINT fk_9f693eab092a811 FOREIGN KEY (store_id) REFERENCES store (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

        $stmt = $this->connection->prepare('SELECT * FROM delivery WHERE store_id IS NOT NULL');

        $result = $stmt->execute();
        while ($delivery = $result->fetchAssociative()) {
            $this->addSql('INSERT INTO store_delivery (store_id, delivery_id) VALUES (:store_id, :delivery_id)', [
                'store_id' => $delivery['store_id'],
                'delivery_id' => $delivery['id'],
            ]);
        }

        $this->addSql('ALTER TABLE delivery DROP CONSTRAINT FK_3781EC10B092A811');
        $this->addSql('DROP INDEX IDX_3781EC10B092A811');
        $this->addSql('ALTER TABLE delivery DROP store_id');
    }
}
