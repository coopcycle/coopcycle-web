<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20211028144524 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mercadopago_account DROP livemode');
        $this->addSql('ALTER TABLE restaurant ALTER is_available_for_b2b SET DEFAULT \'false\'');
        $this->addSql('DROP INDEX restaurant_mercadopago_account_unique');
        $this->addSql('ALTER TABLE restaurant_mercadopago_account DROP livemode');
        $this->addSql('CREATE UNIQUE INDEX restaurant_mercadopago_account_unique ON restaurant_mercadopago_account (restaurant_id, mercadopago_account_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX restaurant_mercadopago_account_unique');
        $this->addSql('ALTER TABLE restaurant_mercadopago_account ADD livemode BOOLEAN NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX restaurant_mercadopago_account_unique ON restaurant_mercadopago_account (restaurant_id, mercadopago_account_id, livemode)');
        $this->addSql('ALTER TABLE mercadopago_account ADD livemode BOOLEAN NOT NULL');
        $this->addSql('ALTER TABLE restaurant ALTER is_available_for_b2b SET DEFAULT \'false\'');
    }
}
