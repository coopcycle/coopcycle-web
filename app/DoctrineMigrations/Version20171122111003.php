<?php

namespace Application\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20171122111003 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE contract (id SERIAL NOT NULL, restaurant_id INT DEFAULT NULL, minimum_cart_amount DOUBLE PRECISION NOT NULL, flat_delivery_price DOUBLE PRECISION NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_E98F2859B1E7706E ON contract (restaurant_id)');
        $this->addSql('ALTER TABLE contract ADD CONSTRAINT FK_E98F2859B1E7706E FOREIGN KEY (restaurant_id) REFERENCES restaurant (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

        $stmt = $this->connection->prepare("SELECT * FROM restaurant");
        $stmt->execute();
        while ($restaurant = $stmt->fetch()) {
            $this->addSql("INSERT INTO contract (restaurant_id, minimum_cart_amount, flat_delivery_price) VALUES (:id, :minimumCartAmount, :flatDeliveryPrice)", [
                'id' => $restaurant['id'],
                'minimumCartAmount' => 15,
                'flatDeliveryPrice' => 3.5
            ]);
        }

        $this->addSql('ALTER TABLE delivery ADD price DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('UPDATE delivery SET price = :price', [ 'price' => 3.5 ]);
        $this->addSql('ALTER TABLE delivery ALTER COLUMN price SET NOT NULL');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');
        $this->addSql('ALTER TABLE delivery DROP price');
        $this->addSql('DROP TABLE contract');
    }
}
