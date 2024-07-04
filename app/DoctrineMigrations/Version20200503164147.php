<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200503164147 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $stmts = [];
        $stmts['users'] =
            $this->connection->prepare('SELECT * FROM api_user');

        $this->addSql('ALTER TABLE api_user ADD customer_id INT DEFAULT NULL');

        $result = $stmts['users']->execute();
        while ($user = $result->fetchAssociative()) {

            $this->addSql('INSERT INTO sylius_customer (email, email_canonical, first_name, last_name, phone_number, subscribed_to_newsletter, created_at, updated_at) VALUES (:email, :email_canonical, :first_name, :last_name, :phone_number, \'f\', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)', [
                'email' => $user['email'],
                'email_canonical' => $user['email_canonical'],
                'first_name' => $user['given_name'],
                'last_name' => $user['family_name'],
                'phone_number' => $user['telephone'],
            ]);

            $this->addSql('UPDATE api_user SET customer_id = (SELECT id FROM sylius_customer WHERE email_canonical = :email_canonical) WHERE email_canonical = :email_canonical', [
                'email_canonical' => $user['email_canonical'],
            ]);
        }

        $this->addSql('ALTER TABLE api_user ALTER COLUMN customer_id SET NOT NULL');
        $this->addSql('ALTER TABLE api_user ADD CONSTRAINT FK_AC64A0BA9395C3F3 FOREIGN KEY (customer_id) REFERENCES sylius_customer (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_AC64A0BA9395C3F3 ON api_user (customer_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE sylius_order DROP CONSTRAINT fk_6196a1f99395c3f3');

        $stmts = [];
        $stmts['orders'] =
            $this->connection->prepare('SELECT o.id, c.email_canonical AS user_email_canonical FROM sylius_order o JOIN sylius_customer c ON o.customer_id = c.id');

        $result = $stmts['orders']->execute();
        while ($order = $result->fetchAssociative()) {
            $this->addSql('UPDATE sylius_order SET customer_id = (SELECT id FROM api_user WHERE email_canonical = :email_canonical) WHERE id = :order_id', [
                'email_canonical' => $order['user_email_canonical'],
                'order_id' => $order['id'],
            ]);
        }

        $this->addSql('ALTER TABLE sylius_order ADD CONSTRAINT fk_6196a1f99395c3f3 FOREIGN KEY (customer_id) REFERENCES api_user (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('ALTER TABLE api_user DROP CONSTRAINT FK_AC64A0BA9395C3F3');
        $this->addSql('DROP INDEX UNIQ_AC64A0BA9395C3F3');
        $this->addSql('ALTER TABLE api_user DROP customer_id');

        $this->addSql('DELETE FROM sylius_customer');
    }
}
