<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200313092450 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE contract ADD variable_delivery_price_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE contract ADD variable_delivery_price_enabled BOOLEAN DEFAULT \'f\' NOT NULL');
        $this->addSql('ALTER TABLE contract ADD CONSTRAINT FK_E98F28595AB39823 FOREIGN KEY (variable_delivery_price_id) REFERENCES pricing_rule_set (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_E98F28595AB39823 ON contract (variable_delivery_price_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE contract DROP CONSTRAINT FK_E98F28595AB39823');
        $this->addSql('DROP INDEX IDX_E98F28595AB39823');
        $this->addSql('ALTER TABLE contract DROP variable_delivery_price_id');
        $this->addSql('ALTER TABLE contract DROP variable_delivery_price_enabled');
    }
}
