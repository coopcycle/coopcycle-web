<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260213072506 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE ui_block (id SERIAL NOT NULL, homepage_id INT DEFAULT NULL, position INT NOT NULL, type VARCHAR(255) NOT NULL, data JSON NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_275D93B6571EDDA ON ui_block (homepage_id)');
        $this->addSql('CREATE TABLE ui_homepage (id SERIAL NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('ALTER TABLE ui_block ADD CONSTRAINT FK_275D93B6571EDDA FOREIGN KEY (homepage_id) REFERENCES ui_homepage (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ui_block DROP CONSTRAINT FK_275D93B6571EDDA');
        $this->addSql('DROP TABLE ui_block');
        $this->addSql('DROP TABLE ui_homepage');
    }
}
