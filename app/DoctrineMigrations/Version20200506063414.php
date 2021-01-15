<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200506063414 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE contract ADD take_away_fee_rate DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('UPDATE contract SET take_away_fee_rate = 0');
        $this->addSql('ALTER TABLE contract ALTER COLUMN take_away_fee_rate SET NOT NULL');

        $this->addSql('ALTER TABLE contract ALTER variable_customer_amount_enabled DROP DEFAULT');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE contract DROP take_away_fee_rate');
        $this->addSql('ALTER TABLE contract ALTER variable_customer_amount_enabled SET DEFAULT \'false\'');
    }
}
