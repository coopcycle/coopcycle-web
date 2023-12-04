<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231116165504 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE edifact_message (id SERIAL NOT NULL, delivery_id INT DEFAULT NULL, transporter VARCHAR(255) NOT NULL, reference VARCHAR(255) NOT NULL, message_type VARCHAR(255) NOT NULL, sub_message_type VARCHAR(255) DEFAULT NULL, edi TEXT DEFAULT NULL, synced_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_DC008BDA12136921 ON edifact_message (delivery_id)');
        $this->addSql('ALTER TABLE edifact_message ADD CONSTRAINT FK_DC008BDA12136921 FOREIGN KEY (delivery_id) REFERENCES delivery (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE edifact_message');
    }
}
