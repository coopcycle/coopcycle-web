<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200901080726 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE sylius_order DROP CONSTRAINT FK_6196A1F99395C3F3');

        $stmt = $this->connection->prepare('SELECT o.id, u.customer_id FROM sylius_order o JOIN api_user u ON o.customer_id = u.id');
        $result = $stmt->execute();
        while ($order = $result->fetchAssociative()) {
            $this->addSql('UPDATE sylius_order SET customer_id = :customer_id WHERE id = :id' , [
                'customer_id' => $order['customer_id'],
                'id' => $order['id'],
            ]);
        }

        $this->addSql('ALTER TABLE sylius_order ADD CONSTRAINT FK_6196A1F99395C3F3 FOREIGN KEY (customer_id) REFERENCES sylius_customer (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE sylius_order DROP CONSTRAINT fk_6196a1f99395c3f3');

        $stmt = $this->connection->prepare('SELECT o.id, u.id AS user_id FROM sylius_order o JOIN sylius_customer c ON o.customer_id = c.id LEFT JOIN api_user u ON c.id = u.customer_id');
        $result = $stmt->execute();
        while ($order = $result->fetchAssociative()) {
            $this->addSql('UPDATE sylius_order SET customer_id = :customer_id WHERE id = :id' , [
                // user_id may be NULL
                'customer_id' => $order['user_id'],
                'id' => $order['id'],
            ]);
        }

        $this->addSql('ALTER TABLE sylius_order ADD CONSTRAINT fk_6196a1f99395c3f3 FOREIGN KEY (customer_id) REFERENCES api_user (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
