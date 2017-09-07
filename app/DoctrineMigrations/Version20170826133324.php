<?php

namespace Application\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20170826133324 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs

        // Recreating addresses linked to a delivery as delivery addresses
        $stmt = $this->connection->prepare("SELECT id, delivery_address_id FROM delivery");
        $stmt->execute();

        while ($res = $stmt->fetch()) {
            $deliveryAddress = $this->connection->execute("SELECT name, street_address, address_locality, postal_code, geo " .
                "FROM address WHERE id = :delivery_address_id", [ "delivery_address_id" => $res['delivery_address_id']]);

            // Create delivery address
            $this->connection->execute("INSERT INTO delivery_address (name, street_address, address_locality, postal_code, geo)"
                . " VALUES (:name, :street_address, :address_locality, :postal_code, :geo)", $deliveryAddress);

            // Inserting new ref on delivery table
            $this->addSql("UPDATE api_user_address SET delivery_address_temp_id = CURRVAL('delivery_address_id') WHERE id = :id",
                 ['id' => $res['id']]);
        }

    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        // Nothing to do here, next migration down will drop everything if needed

    }
}
