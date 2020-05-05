<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200504055738 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE sylius_customer_address (customer_id INT NOT NULL, address_id INT NOT NULL, PRIMARY KEY(customer_id, address_id))');
        $this->addSql('CREATE INDEX IDX_D0D865859395C3F3 ON sylius_customer_address (customer_id)');
        $this->addSql('CREATE INDEX IDX_D0D86585F5B7AF75 ON sylius_customer_address (address_id)');
        $this->addSql('ALTER TABLE sylius_customer_address ADD CONSTRAINT FK_D0D865859395C3F3 FOREIGN KEY (customer_id) REFERENCES sylius_customer (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sylius_customer_address ADD CONSTRAINT FK_D0D86585F5B7AF75 FOREIGN KEY (address_id) REFERENCES address (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('ALTER TABLE sylius_customer ADD default_address_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE sylius_customer ADD CONSTRAINT FK_7E82D5E6BD94FB16 FOREIGN KEY (default_address_id) REFERENCES address (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('DROP INDEX IF EXISTS uniq_7e82d5e6bd94fb16');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_7E82D5E6BD94FB16 ON sylius_customer (default_address_id)');

        $stmts = [];
        $stmts['user_address'] =
            $this->connection->prepare('SELECT a.address_id, u.customer_id FROM api_user_address a JOIN api_user u ON a.api_user_id = u.id');
        $stmts['default_address'] =
            $this->connection->prepare('select shipping_address_id AS id, count(*) AS number_of_orders from sylius_order
where customer_id = :customer_id AND shipping_address_id IS NOT NULL GROUP BY shipping_address_id ORDER BY number_of_orders DESC LIMIT 1');

        $customersWithAddress = [];

        $stmts['user_address']->execute();
        while ($userAddress = $stmts['user_address']->fetch()) {

            $this->addSql('INSERT INTO sylius_customer_address (customer_id, address_id) VALUES (:customer_id, :address_id)' , [
                'customer_id' => $userAddress['customer_id'],
                'address_id' => $userAddress['address_id'],
            ]);

            $customersWithAddress[] = $userAddress['customer_id'];
        }

        $customersWithAddress = array_values(array_unique($customersWithAddress));

        foreach ($customersWithAddress as $customer_id) {
            $stmts['default_address']->bindParam('customer_id', $customer_id);
            $stmts['default_address']->execute();

            $defaultAddress = $stmts['default_address']->fetch();

            if ($stmts['default_address']->rowCount() === 0) {
                continue;
            }

            $this->addSql('UPDATE sylius_customer SET default_address_id = :default_address_id WHERE id = :customer_id' , [
                'default_address_id' => $defaultAddress['id'],
                'customer_id' => $customer_id,
            ]);
        }

        $this->addSql('DROP TABLE api_user_address');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE api_user_address (api_user_id INT NOT NULL, address_id INT NOT NULL, PRIMARY KEY(api_user_id, address_id))');
        $this->addSql('CREATE INDEX idx_5f29a826f5b7af75 ON api_user_address (address_id)');
        $this->addSql('CREATE INDEX idx_5f29a8264a50a7f2 ON api_user_address (api_user_id)');
        $this->addSql('ALTER TABLE api_user_address ADD CONSTRAINT fk_5f29a826f5b7af75 FOREIGN KEY (address_id) REFERENCES address (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE api_user_address ADD CONSTRAINT fk_5f29a8264a50a7f2 FOREIGN KEY (api_user_id) REFERENCES api_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $stmts = [];
        $stmts['customer_address'] =
            $this->connection->prepare('SELECT a.address_id, u.id AS user_id FROM sylius_customer_address a JOIN sylius_customer c ON c.id = a.customer_id JOIN api_user u ON c.id = u.customer_id');

        $stmts['customer_address']->execute();
        while ($customerAddress = $stmts['customer_address']->fetch()) {
            $this->addSql('INSERT INTO api_user_address (api_user_id, address_id) VALUES (:user_id, :address_id)' , [
                'user_id' => $customerAddress['user_id'],
                'address_id' => $customerAddress['address_id'],
            ]);
        }

        $this->addSql('DROP TABLE sylius_customer_address');
        $this->addSql('ALTER TABLE sylius_customer DROP CONSTRAINT FK_7E82D5E6BD94FB16');
        $this->addSql('DROP INDEX IDX_7E82D5E6BD94FB16');
        $this->addSql('ALTER TABLE sylius_customer DROP default_address_id');
    }
}
