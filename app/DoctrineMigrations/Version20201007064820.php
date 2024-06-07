<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20201007064820 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE sylius_customer_loopeat_credentials (id SERIAL NOT NULL, customer_id INT NOT NULL, loopeat_access_token TEXT DEFAULT NULL, loopeat_refresh_token TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_40E6995A9395C3F3 ON sylius_customer_loopeat_credentials (customer_id)');
        $this->addSql('ALTER TABLE sylius_customer_loopeat_credentials ADD CONSTRAINT FK_40E6995A9395C3F3 FOREIGN KEY (customer_id) REFERENCES sylius_customer (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

        $stmt = $this->connection->prepare('SELECT id, customer_id, loopeat_access_token, loopeat_refresh_token FROM api_user WHERE loopeat_access_token IS NOT NULL OR loopeat_refresh_token IS NOT NULL');

        $result = $stmt->execute();
        while ($user = $result->fetchAssociative()) {
            $this->addSql('INSERT INTO sylius_customer_loopeat_credentials (customer_id, loopeat_access_token, loopeat_refresh_token, created_at, updated_at) VALUES (:customer_id, :loopeat_access_token, :loopeat_refresh_token, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)' , [
                'customer_id' => $user['customer_id'],
                'loopeat_access_token' => $user['loopeat_access_token'],
                'loopeat_refresh_token' => $user['loopeat_refresh_token'],
            ]);
        }

        $this->addSql('ALTER TABLE api_user DROP loopeat_access_token');
        $this->addSql('ALTER TABLE api_user DROP loopeat_refresh_token');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE api_user ADD loopeat_access_token TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE api_user ADD loopeat_refresh_token TEXT DEFAULT NULL');

        $stmt = $this->connection->prepare('SELECT customer_id, loopeat_access_token, loopeat_refresh_token FROM sylius_customer_loopeat_credentials');

        $result = $stmt->execute();
        while ($credentials = $result->fetchAssociative()) {
            $this->addSql('UPDATE api_user SET loopeat_access_token = :loopeat_access_token, loopeat_refresh_token = :loopeat_refresh_token WHERE customer_id = :customer_id', [
                'loopeat_access_token' => $credentials['loopeat_access_token'],
                'loopeat_refresh_token' => $credentials['loopeat_refresh_token'],
                'customer_id' => $credentials['customer_id'],
            ]);
        }

        $this->addSql('DROP TABLE sylius_customer_loopeat_credentials');
    }
}
