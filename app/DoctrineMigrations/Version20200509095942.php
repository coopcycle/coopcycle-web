<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200509095942 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE restaurant_fulfillment_method (id SERIAL NOT NULL, restaurant_id INT DEFAULT NULL, type VARCHAR(255) NOT NULL, opening_hours JSON NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_CA44E9E4B1E7706E ON restaurant_fulfillment_method (restaurant_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_CA44E9E4B1E7706E8CDE5729 ON restaurant_fulfillment_method (restaurant_id, type)');
        $this->addSql('COMMENT ON COLUMN restaurant_fulfillment_method.opening_hours IS \'(DC2Type:json_array)\'');
        $this->addSql('ALTER TABLE restaurant_fulfillment_method ADD CONSTRAINT FK_CA44E9E4B1E7706E FOREIGN KEY (restaurant_id) REFERENCES restaurant (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP TABLE restaurant_fulfillment_method');
    }
}
