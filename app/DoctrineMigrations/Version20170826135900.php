<?php

namespace Application\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20170826135900 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE delivery DROP CONSTRAINT FK_3781EC10EBF23851');
        $this->addSql('ALTER TABLE delivery DROP COLUMN delivery_address_id');
        $this->addSql('ALTER TABLE delivery DROP RENAME COLUMN delivery_address_temp_id TO delivery_address_id');


    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE delivery DROP RENAME COLUMN delivery_address_id TO  delivery_address_temp_id');
        $this->addSql('ALTER TABLE delivery ADD CONSTRAINT fk_3781ec10ebf23851 FOREIGN KEY (delivery_address_id) REFERENCES address (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE delivery ADD COLUMN delivery_address_id');
    }
}
