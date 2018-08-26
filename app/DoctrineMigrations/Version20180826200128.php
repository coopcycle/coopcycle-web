<?php declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20180826200128 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('DROP SEQUENCE stripe_transfer_id_seq CASCADE');
        $this->addSql('DROP TABLE stripe_transfer');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SEQUENCE stripe_transfer_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE stripe_transfer (id SERIAL NOT NULL, stripe_payment_id INT DEFAULT NULL, stripe_account_id INT NOT NULL, transfer VARCHAR(255) DEFAULT NULL, transfer_group VARCHAR(255) DEFAULT NULL, currency_code VARCHAR(3) NOT NULL, amount INT NOT NULL, state VARCHAR(255) NOT NULL, details JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_1d737ff3e065f932 ON stripe_transfer (stripe_account_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_1d737ff37fcd0533 ON stripe_transfer (stripe_payment_id)');
        $this->addSql('COMMENT ON COLUMN stripe_transfer.details IS \'(DC2Type:json_array)\'');
        $this->addSql('ALTER TABLE stripe_transfer ADD CONSTRAINT fk_1d737ff3e065f932 FOREIGN KEY (stripe_account_id) REFERENCES stripe_account (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE stripe_transfer ADD CONSTRAINT fk_1d737ff37fcd0533 FOREIGN KEY (stripe_payment_id) REFERENCES stripe_payment (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
