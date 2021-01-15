<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20201001132956 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Make sure user/customer emails are aligned';
    }

    public function up(Schema $schema) : void
    {
        $stmt = $this->connection->prepare('SELECT '.
            'u.id AS user_id, u.email AS user_email, u.email_canonical AS user_email_canonical, '.
            'c.id customer_id, c.email AS customer_email, c.email_canonical AS customer_email_canonical '.
            'FROM api_user u JOIN sylius_customer c ON u.customer_id = c.id '.
            'WHERE u.email != c.email OR u.email_canonical != c.email_canonical');

        $stmt->execute();
        while ($data = $stmt->fetch()) {

            if ($data['customer_email'] !== $data['user_email']) {
                $this->addSql('UPDATE sylius_customer SET email = :email WHERE id = :id' , [
                    'email' => $data['user_email'],
                    'id' => $data['customer_id'],
                ]);
            }

            if ($data['customer_email_canonical'] !== $data['user_email_canonical']) {
                $this->addSql('UPDATE sylius_customer SET email_canonical = :email_canonical WHERE id = :id' , [
                    'email_canonical' => $data['user_email_canonical'],
                    'id' => $data['customer_id'],
                ]);
            }
        }
    }

    public function down(Schema $schema) : void
    {

    }
}
