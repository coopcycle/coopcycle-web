<?php

namespace Application\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20170604135023 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE SEQUENCE stripe_params_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE api_user_stripe_params (api_user_id INT NOT NULL, stripe_params_id INT NOT NULL, PRIMARY KEY(api_user_id, stripe_params_id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B16653364A50A7F2 ON api_user_stripe_params (api_user_id)');
        $this->addSql('CREATE INDEX IDX_B16653368C11ECC5 ON api_user_stripe_params (stripe_params_id)');
        $this->addSql('CREATE TABLE stripe_params (id INT NOT NULL, user_id VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('ALTER TABLE api_user_stripe_params ADD CONSTRAINT FK_B16653364A50A7F2 FOREIGN KEY (api_user_id) REFERENCES api_user (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE api_user_stripe_params ADD CONSTRAINT FK_B16653368C11ECC5 FOREIGN KEY (stripe_params_id) REFERENCES stripe_params (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE restaurant ADD stripe_params_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE restaurant ADD CONSTRAINT FK_EB95123F8C11ECC5 FOREIGN KEY (stripe_params_id) REFERENCES stripe_params (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_EB95123F8C11ECC5 ON restaurant (stripe_params_id)');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE api_user_stripe_params DROP CONSTRAINT FK_B16653368C11ECC5');
        $this->addSql('ALTER TABLE restaurant DROP CONSTRAINT FK_EB95123F8C11ECC5');
        $this->addSql('DROP SEQUENCE stripe_params_id_seq CASCADE');
        $this->addSql('DROP TABLE api_user_stripe_params');
        $this->addSql('DROP TABLE stripe_params');
        $this->addSql('DROP INDEX IDX_EB95123F8C11ECC5');
        $this->addSql('ALTER TABLE restaurant DROP stripe_params_id');
    }
}
