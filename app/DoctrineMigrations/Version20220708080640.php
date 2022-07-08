<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220708080640 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE delivery_quote (id SERIAL NOT NULL, store_id INT NOT NULL, delivery_id INT DEFAULT NULL, state VARCHAR(255) NOT NULL, amount INT NOT NULL, payload JSON NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_7B7A2D66B092A811 ON delivery_quote (store_id)');
        $this->addSql('CREATE INDEX IDX_7B7A2D6612136921 ON delivery_quote (delivery_id)');
        $this->addSql('COMMENT ON COLUMN delivery_quote.payload IS \'(DC2Type:json_array)\'');
        $this->addSql('ALTER TABLE delivery_quote ADD CONSTRAINT FK_7B7A2D66B092A811 FOREIGN KEY (store_id) REFERENCES store (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE delivery_quote ADD CONSTRAINT FK_7B7A2D6612136921 FOREIGN KEY (delivery_id) REFERENCES delivery (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE delivery_quote');
    }
}
