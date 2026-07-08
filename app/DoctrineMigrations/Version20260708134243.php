<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260708134243 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE cyke_delivery (id SERIAL NOT NULL, delivery_id INT NOT NULL, cyke_id VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_BEDD28E112136921 ON cyke_delivery (delivery_id)');
        $this->addSql('ALTER TABLE cyke_delivery ADD CONSTRAINT FK_BEDD28E112136921 FOREIGN KEY (delivery_id) REFERENCES delivery (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE store ADD cyke_user_email VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE store ADD cyke_user_token VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cyke_delivery DROP CONSTRAINT FK_BEDD28E112136921');
        $this->addSql('DROP TABLE cyke_delivery');
        $this->addSql('ALTER TABLE store DROP cyke_user_email');
        $this->addSql('ALTER TABLE store DROP cyke_user_token');
    }
}
