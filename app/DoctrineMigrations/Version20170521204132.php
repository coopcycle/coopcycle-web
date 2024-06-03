<?php

namespace Application\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20170521204132 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE restaurant ADD address_id INT DEFAULT NULL');

        $stmt = $this->connection->prepare("SELECT * FROM restaurant");
        $result = $stmt->execute();

        while ($restaurant = $result->fetchAssociative()) {
            $this->addSql("INSERT INTO address (id, name, street_address, address_locality, postal_code, geo)"
                . " VALUES (nextval('address_id_seq'), :name, :street_address, :address_locality, :postal_code, :geo)", $restaurant);
            $this->addSql("UPDATE restaurant SET address_id = currval('address_id_seq') WHERE id = :id", $restaurant);
        }

        $this->addSql('ALTER TABLE restaurant ADD CONSTRAINT FK_EB95123FF5B7AF75 FOREIGN KEY (address_id) REFERENCES address (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_EB95123FF5B7AF75 ON restaurant (address_id)');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE restaurant DROP CONSTRAINT FK_EB95123FF5B7AF75');
        $this->addSql('DROP INDEX UNIQ_EB95123FF5B7AF75');

        $this->addSql('ALTER TABLE restaurant DROP address_id');
    }
}
