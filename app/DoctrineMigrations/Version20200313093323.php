<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200313093323 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE contract ADD variable_customer_amount_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE contract ADD variable_customer_amount_enabled BOOLEAN DEFAULT \'f\' NOT NULL');
        $this->addSql('ALTER TABLE contract ALTER variable_delivery_price_enabled DROP DEFAULT');
        $this->addSql('ALTER TABLE contract ADD CONSTRAINT FK_E98F2859E8AC63E6 FOREIGN KEY (variable_customer_amount_id) REFERENCES pricing_rule_set (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_E98F2859E8AC63E6 ON contract (variable_customer_amount_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE contract DROP CONSTRAINT FK_E98F2859E8AC63E6');
        $this->addSql('DROP INDEX IDX_E98F2859E8AC63E6');
        $this->addSql('ALTER TABLE contract DROP variable_customer_amount_id');
        $this->addSql('ALTER TABLE contract DROP variable_customer_amount_enabled');
        $this->addSql('ALTER TABLE contract ALTER variable_delivery_price_enabled SET DEFAULT \'false\'');
    }
}
