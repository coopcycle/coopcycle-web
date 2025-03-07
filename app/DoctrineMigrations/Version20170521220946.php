<?php

namespace Application\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20170521220946 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE api_user_address (api_user_id INT NOT NULL, address_id INT NOT NULL, PRIMARY KEY(api_user_id, address_id))');
        $this->addSql('CREATE INDEX IDX_5F29A8264A50A7F2 ON api_user_address (api_user_id)');
        $this->addSql('CREATE INDEX IDX_5F29A826F5B7AF75 ON api_user_address (address_id)');

        $stmt = $this->connection->prepare("SELECT * FROM delivery_address");
        $result = $stmt->execute();

        while ($deliveryAddress = $result->fetchAssociative()) {
            $this->addSql("INSERT INTO address (id, name, street_address, address_locality, postal_code, geo)"
                . " VALUES (nextval('address_id_seq'), :name, :street_address, :address_locality, :postal_code, :geo)", $deliveryAddress);
            $this->addSql("INSERT INTO api_user_address (api_user_id, address_id) VALUES (:api_user_id, currval('address_id_seq'))", [
                'api_user_id' => $deliveryAddress['customer_id']
            ]);
        }

        $this->addSql('ALTER TABLE api_user_address ADD CONSTRAINT FK_5F29A8264A50A7F2 FOREIGN KEY (api_user_id) REFERENCES api_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE api_user_address ADD CONSTRAINT FK_5F29A826F5B7AF75 FOREIGN KEY (address_id) REFERENCES address (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('ALTER TABLE address DROP CONSTRAINT fk_d4e6f81a76ed395');
        $this->addSql('DROP INDEX idx_d4e6f81a76ed395');
        $this->addSql('ALTER TABLE address DROP user_id');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE address ADD user_id INT DEFAULT NULL');

        $stmt = $this->connection->prepare("SELECT * FROM api_user_address");
        $result = $stmt->execute();

        while ($address = $result->fetchAssociative()) {
            $this->addSql("UPDATE address SET user_id = :api_user_id WHERE id = :address_id", $address);
        }

        $this->addSql('ALTER TABLE address ADD CONSTRAINT fk_d4e6f81a76ed395 FOREIGN KEY (user_id) REFERENCES api_user (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_d4e6f81a76ed395 ON address (user_id)');

        $this->addSql('DROP TABLE api_user_address');
    }
}
