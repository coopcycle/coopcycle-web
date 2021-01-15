<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200819183413 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE restaurant_mercadopago_account (id SERIAL NOT NULL, restaurant_id INT DEFAULT NULL, mercadopago_account_id INT DEFAULT NULL, livemode BOOLEAN NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_8EFBA10EB1E7706E ON restaurant_mercadopago_account (restaurant_id)');
        $this->addSql('CREATE INDEX IDX_8EFBA10E295E6E66 ON restaurant_mercadopago_account (mercadopago_account_id)');
        $this->addSql('CREATE UNIQUE INDEX restaurant_mercadopago_account_unique ON restaurant_mercadopago_account (restaurant_id, mercadopago_account_id, livemode)');
        $this->addSql('CREATE TABLE mercadopago_account (id SERIAL NOT NULL, user_id VARCHAR(255) NOT NULL, access_token TEXT NOT NULL, refresh_token TEXT NOT NULL, livemode BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE api_user_mercadopago_account (user_id INT NOT NULL, mercadopago_account_id INT NOT NULL, PRIMARY KEY(user_id, mercadopago_account_id))');
        $this->addSql('CREATE INDEX IDX_E0667F15A76ED395 ON api_user_mercadopago_account (user_id)');
        $this->addSql('CREATE INDEX IDX_E0667F15295E6E66 ON api_user_mercadopago_account (mercadopago_account_id)');
        $this->addSql('ALTER TABLE restaurant_mercadopago_account ADD CONSTRAINT FK_8EFBA10EB1E7706E FOREIGN KEY (restaurant_id) REFERENCES restaurant (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE restaurant_mercadopago_account ADD CONSTRAINT FK_8EFBA10E295E6E66 FOREIGN KEY (mercadopago_account_id) REFERENCES mercadopago_account (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE api_user_mercadopago_account ADD CONSTRAINT FK_E0667F15A76ED395 FOREIGN KEY (user_id) REFERENCES api_user (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE api_user_mercadopago_account ADD CONSTRAINT FK_E0667F15295E6E66 FOREIGN KEY (mercadopago_account_id) REFERENCES mercadopago_account (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE restaurant_mercadopago_account DROP CONSTRAINT FK_8EFBA10E295E6E66');
        $this->addSql('ALTER TABLE api_user_mercadopago_account DROP CONSTRAINT FK_E0667F15295E6E66');
        $this->addSql('DROP TABLE restaurant_mercadopago_account');
        $this->addSql('DROP TABLE mercadopago_account');
        $this->addSql('DROP TABLE api_user_mercadopago_account');
    }
}
