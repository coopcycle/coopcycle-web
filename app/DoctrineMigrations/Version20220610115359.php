<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220610115359 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE sylius_customer_dabba_credentials (id SERIAL NOT NULL, customer_id INT NOT NULL, access_token TEXT DEFAULT NULL, refresh_token TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6A1688CF9395C3F3 ON sylius_customer_dabba_credentials (customer_id)');
        $this->addSql('ALTER TABLE sylius_customer_dabba_credentials ADD CONSTRAINT FK_6A1688CF9395C3F3 FOREIGN KEY (customer_id) REFERENCES sylius_customer (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('ALTER TABLE restaurant ADD dabba_enabled BOOLEAN DEFAULT \'false\' NOT NULL');
        $this->addSql('ALTER TABLE restaurant ADD dabba_code VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE sylius_customer_dabba_credentials');

        $this->addSql('ALTER TABLE restaurant DROP dabba_enabled');
        $this->addSql('ALTER TABLE restaurant DROP dabba_code');
    }
}
