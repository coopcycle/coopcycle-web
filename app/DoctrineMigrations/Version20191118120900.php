<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20191118120900 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE invoice_line_item (id SERIAL NOT NULL, invoice_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, quantity INT NOT NULL, unit_price INT NOT NULL, subtotal INT NOT NULL, tax_total INT NOT NULL, total INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_F1F9275B2989F1FD ON invoice_line_item (invoice_id)');
        $this->addSql('CREATE TABLE invoice (id SERIAL NOT NULL, number VARCHAR(255) NOT NULL, order_number VARCHAR(255) NOT NULL, issued_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, total INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('ALTER TABLE invoice_line_item ADD CONSTRAINT FK_F1F9275B2989F1FD FOREIGN KEY (invoice_id) REFERENCES invoice (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE invoice_line_item DROP CONSTRAINT FK_F1F9275B2989F1FD');
        $this->addSql('DROP TABLE invoice_line_item');
        $this->addSql('DROP TABLE invoice');
    }
}
