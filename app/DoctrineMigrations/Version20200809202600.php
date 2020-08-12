<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200809202600 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE api_user ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE api_user ADD updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');

        $stmt = $this->connection->prepare('SELECT c.created_at, c.updated_at, u.id AS user_id FROM sylius_customer c JOIN api_user u ON c.id = u.customer_id');

        $stmt->execute();

        while ($customer = $stmt->fetch()) {
            $this->addSql('UPDATE api_user SET created_at = :created_at, updated_at = :updated_at WHERE id = :id', [
                'created_at' => $customer['created_at'],
                'updated_at' => $customer['updated_at'],
                'id' => $customer['user_id'],
            ]);
        }

        $this->addSql('ALTER TABLE api_user ALTER created_at SET NOT NULL');
        $this->addSql('ALTER TABLE api_user ALTER updated_at SET NOT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE api_user DROP created_at');
        $this->addSql('ALTER TABLE api_user DROP updated_at');
    }
}
