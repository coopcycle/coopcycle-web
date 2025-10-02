<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251002081202 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $hash = [];

        $stmt = $this->connection->prepare('SELECT id, restaurant_id FROM mercadopago_account');
        $result = $stmt->execute();
        while ($mpAccount = $result->fetchAssociative()) {
            $hash[$mpAccount['restaurant_id']] = $mpAccount['id'];
        }

        $this->addSql('ALTER TABLE mercadopago_account DROP CONSTRAINT fk_b056d88b1e7706e');
        $this->addSql('DROP INDEX uniq_b056d88b1e7706e');

        $this->addSql('ALTER TABLE mercadopago_account DROP restaurant_id');
        $this->addSql('ALTER TABLE restaurant ADD mercadopago_account_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE restaurant ADD CONSTRAINT FK_EB95123F295E6E66 FOREIGN KEY (mercadopago_account_id) REFERENCES mercadopago_account (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_EB95123F295E6E66 ON restaurant (mercadopago_account_id)');

        foreach ($hash as $restaurantId => $mpAccountId) {
            $this->addSql('UPDATE restaurant SET mercadopago_account_id = :account_id WHERE id = :id', [
                'account_id' => $mpAccountId,
                'id' => $restaurantId
            ]);
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mercadopago_account ADD restaurant_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE mercadopago_account ADD CONSTRAINT fk_b056d88b1e7706e FOREIGN KEY (restaurant_id) REFERENCES restaurant (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX uniq_b056d88b1e7706e ON mercadopago_account (restaurant_id)');
        $this->addSql('ALTER TABLE restaurant DROP CONSTRAINT FK_EB95123F295E6E66');
        $this->addSql('DROP INDEX UNIQ_EB95123F295E6E66');
        $this->addSql('ALTER TABLE restaurant DROP mercadopago_account_id');
    }
}
