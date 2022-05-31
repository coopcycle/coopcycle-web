<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20220527160534 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mercadopago_account ADD restaurant_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE mercadopago_account ADD CONSTRAINT FK_B056D88B1E7706E FOREIGN KEY (restaurant_id) REFERENCES restaurant (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B056D88B1E7706E ON mercadopago_account (restaurant_id)');

        // set restaurant_id with proper reference
        $restaurantMPAccounts = $this->connection->prepare
        (
            'SELECT restaurant_id, mercadopago_account_id FROM restaurant_mercadopago_account'
        );
        $restaurantMPAccounts->execute();

        while ($restaurantMPAccount = $restaurantMPAccounts->fetch())
        {
            $this->addSql('UPDATE mercadopago_account SET restaurant_id=:restaurant_id WHERE id=:mercadopago_account_id', [
                'restaurant_id' => $restaurantMPAccount['restaurant_id'],
                'mercadopago_account_id' => $restaurantMPAccount['mercadopago_account_id']
            ]);
        }

        $this->addSql('DROP SEQUENCE restaurant_mercadopago_account_id_seq CASCADE');
        $this->addSql('DROP TABLE restaurant_mercadopago_account');

        // we don't need in the user a reference to mercadopago_account
        $this->addSql('DROP TABLE api_user_mercadopago_account');

        $this->addSql('ALTER TABLE mercadopago_account ALTER COLUMN restaurant_id SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE SEQUENCE restaurant_mercadopago_account_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE restaurant_mercadopago_account (id SERIAL NOT NULL, restaurant_id INT DEFAULT NULL, mercadopago_account_id INT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_8efba10e295e6e66 ON restaurant_mercadopago_account (mercadopago_account_id)');
        $this->addSql('CREATE UNIQUE INDEX restaurant_mercadopago_account_unique ON restaurant_mercadopago_account (restaurant_id, mercadopago_account_id)');
        $this->addSql('CREATE INDEX idx_8efba10eb1e7706e ON restaurant_mercadopago_account (restaurant_id)');
        $this->addSql('ALTER TABLE restaurant_mercadopago_account ADD CONSTRAINT fk_8efba10e295e6e66 FOREIGN KEY (mercadopago_account_id) REFERENCES mercadopago_account (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE restaurant_mercadopago_account ADD CONSTRAINT fk_8efba10eb1e7706e FOREIGN KEY (restaurant_id) REFERENCES restaurant (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

        $mpAccounts = $this->connection->prepare
        (
            'SELECT id, restaurant_id FROM mercadopago_account WHERE restaurant_id IS NOT NULL'
        );
        $mpAccounts->execute();

        while ($mpAccount = $mpAccounts->fetch())
        {
            $this->addSql('INSERT INTO restaurant_mercadopago_account
                (restaurant_id, mercadopago_account_id) VALUES
                (:restaurant_id, :mercadopago_account_id)', [
                'restaurant_id' => $mpAccount['restaurant_id'],
                'mercadopago_account_id' => $mpAccount['id']
            ]);
        }

        $this->addSql('ALTER TABLE mercadopago_account DROP CONSTRAINT FK_B056D88B1E7706E');
        $this->addSql('DROP INDEX UNIQ_B056D88B1E7706E');
        $this->addSql('ALTER TABLE mercadopago_account DROP restaurant_id');
    }
}
