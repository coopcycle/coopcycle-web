<?php

namespace Application\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20170603135648 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE order_ DROP CONSTRAINT fk_d7f7910debf23851');
        $this->addSql('DROP SEQUENCE delivery_address_id_seq CASCADE');
        $this->addSql('DROP TABLE delivery_address');
        $this->addSql('DROP INDEX idx_d7f7910debf23851');
        $this->addSql('ALTER TABLE order_ DROP delivery_address_id');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SEQUENCE delivery_address_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE delivery_address (id INT NOT NULL, customer_id INT DEFAULT NULL, name VARCHAR(255) DEFAULT NULL, description VARCHAR(255) DEFAULT NULL, geo geography(GEOMETRY, 4326) DEFAULT NULL, address_country VARCHAR(255) DEFAULT NULL, address_locality VARCHAR(255) DEFAULT NULL, address_region VARCHAR(255) DEFAULT NULL, postal_code VARCHAR(255) DEFAULT NULL, post_office_box_number VARCHAR(255) DEFAULT NULL, street_address VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_750d05f9395c3f3 ON delivery_address (customer_id)');
        $this->addSql('COMMENT ON COLUMN delivery_address.geo IS \'(DC2Type:geography)\'');
        $this->addSql('CREATE INDEX idx_delivery_address_geo ON delivery_address USING gist(geo)');
        $this->addSql('ALTER TABLE delivery_address ADD CONSTRAINT fk_750d05f9395c3f3 FOREIGN KEY (customer_id) REFERENCES api_user (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE order_ ADD delivery_address_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE order_ ADD CONSTRAINT fk_d7f7910debf23851 FOREIGN KEY (delivery_address_id) REFERENCES delivery_address (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_d7f7910debf23851 ON order_ (delivery_address_id)');
    }
}
