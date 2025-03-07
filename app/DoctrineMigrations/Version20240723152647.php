<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240723152647 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE sylius_customer_paygreen_details (id SERIAL NOT NULL, customer_id INT NOT NULL, buyer_id VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_38E472FA9395C3F3 ON sylius_customer_paygreen_details (customer_id)');
        $this->addSql('ALTER TABLE sylius_customer_paygreen_details ADD CONSTRAINT FK_38E472FA9395C3F3 FOREIGN KEY (customer_id) REFERENCES sylius_customer (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_customer_paygreen_details DROP CONSTRAINT FK_38E472FA9395C3F3');
        $this->addSql('DROP TABLE sylius_customer_paygreen_details');
    }
}
