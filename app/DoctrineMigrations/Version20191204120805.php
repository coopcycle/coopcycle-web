<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20191204120805 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE sylius_order_receipt (id SERIAL NOT NULL, order_id INT DEFAULT NULL, issued_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_49442B208D9F6D38 ON sylius_order_receipt (order_id)');
        $this->addSql('CREATE TABLE sylius_order_receipt_footer_item (id SERIAL NOT NULL, parent_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, total INT NOT NULL, position INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_45E37DF6727ACA70 ON sylius_order_receipt_footer_item (parent_id)');
        $this->addSql('CREATE TABLE sylius_order_receipt_line_item (id SERIAL NOT NULL, parent_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, quantity INT NOT NULL, unit_price INT NOT NULL, subtotal INT NOT NULL, tax_total INT NOT NULL, total INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_EA7794C9727ACA70 ON sylius_order_receipt_line_item (parent_id)');
        $this->addSql('ALTER TABLE sylius_order_receipt ADD CONSTRAINT FK_49442B208D9F6D38 FOREIGN KEY (order_id) REFERENCES sylius_order (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sylius_order_receipt_footer_item ADD CONSTRAINT FK_45E37DF6727ACA70 FOREIGN KEY (parent_id) REFERENCES sylius_order_receipt (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sylius_order_receipt_line_item ADD CONSTRAINT FK_EA7794C9727ACA70 FOREIGN KEY (parent_id) REFERENCES sylius_order_receipt (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE sylius_order_receipt_footer_item DROP CONSTRAINT FK_45E37DF6727ACA70');
        $this->addSql('ALTER TABLE sylius_order_receipt_line_item DROP CONSTRAINT FK_EA7794C9727ACA70');
        $this->addSql('DROP TABLE sylius_order_receipt');
        $this->addSql('DROP TABLE sylius_order_receipt_footer_item');
        $this->addSql('DROP TABLE sylius_order_receipt_line_item');
    }
}
