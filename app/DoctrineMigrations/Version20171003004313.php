<?php

namespace Application\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20171003004313 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE delivery_service (id SERIAL NOT NULL, options JSON DEFAULT NULL, type VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('ALTER TABLE restaurant ADD delivery_service_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE restaurant DROP delivery_service');
        $this->addSql('ALTER TABLE restaurant ADD CONSTRAINT FK_EB95123FF3193EC2 FOREIGN KEY (delivery_service_id) REFERENCES delivery_service (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_EB95123FF3193EC2 ON restaurant (delivery_service_id)');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE restaurant DROP CONSTRAINT FK_EB95123FF3193EC2');
        $this->addSql('DROP INDEX UNIQ_EB95123FF3193EC2');
        $this->addSql('ALTER TABLE restaurant ADD delivery_service VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE restaurant DROP delivery_service_id');
        $this->addSql('DROP TABLE delivery_service');
    }
}
