<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180408154129 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE api_user_stripe_account (user_id INT NOT NULL, stripe_account_id INT NOT NULL, PRIMARY KEY(user_id, stripe_account_id))');
        $this->addSql('CREATE INDEX IDX_9606C8C6A76ED395 ON api_user_stripe_account (user_id)');
        $this->addSql('CREATE INDEX IDX_9606C8C6E065F932 ON api_user_stripe_account (stripe_account_id)');
        $this->addSql('CREATE TABLE stripe_account (id SERIAL NOT NULL, type VARCHAR(255) NOT NULL, display_name VARCHAR(255) NOT NULL, transfers_enabled BOOLEAN NOT NULL, stripe_user_id VARCHAR(255) NOT NULL, refresh_token VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('ALTER TABLE api_user_stripe_account ADD CONSTRAINT FK_9606C8C6A76ED395 FOREIGN KEY (user_id) REFERENCES api_user (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE api_user_stripe_account ADD CONSTRAINT FK_9606C8C6E065F932 FOREIGN KEY (stripe_account_id) REFERENCES stripe_account (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('ALTER TABLE store DROP CONSTRAINT fk_ff5758778c11ecc5');
        $this->addSql('ALTER TABLE restaurant DROP CONSTRAINT fk_eb95123f8c11ecc5');
        $this->addSql('ALTER TABLE api_user_stripe_params DROP CONSTRAINT fk_b16653368c11ecc5');

        $this->addSql('DROP INDEX idx_eb95123f8c11ecc5');
        $this->addSql('ALTER TABLE restaurant RENAME COLUMN stripe_params_id TO stripe_account_id');
        $this->addSql('ALTER TABLE restaurant ADD CONSTRAINT FK_EB95123FE065F932 FOREIGN KEY (stripe_account_id) REFERENCES stripe_account (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_EB95123FE065F932 ON restaurant (stripe_account_id)');

        $this->addSql('DROP INDEX idx_ff5758778c11ecc5');
        $this->addSql('ALTER TABLE store RENAME COLUMN stripe_params_id TO stripe_account_id');
        $this->addSql('ALTER TABLE store ADD CONSTRAINT FK_FF575877E065F932 FOREIGN KEY (stripe_account_id) REFERENCES stripe_account (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_FF575877E065F932 ON store (stripe_account_id)');

        $this->addSql('DROP SEQUENCE stripe_params_id_seq CASCADE');
        $this->addSql('DROP TABLE api_user_stripe_params');
        $this->addSql('DROP TABLE stripe_params');
    }

    public function down(Schema $schema) : void
    {
    }
}
