<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210219143242 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        $this->addSql('UPDATE sylius_payment_method_translation SET name = :name WHERE translatable_id = (SELECT id FROM sylius_payment_method WHERE code = :code)', [
            'name' => 'Card',
            'code' => 'STRIPE',
        ]);
        $this->addSql('UPDATE sylius_payment_method SET code = :new WHERE code = :old', [
            'new' => 'CARD',
            'old' => 'STRIPE',
        ]);
    }

    public function down(Schema $schema) : void
    {
        $this->addSql('UPDATE sylius_payment_method_translation SET name = :name WHERE translatable_id = (SELECT id FROM sylius_payment_method WHERE code = :code)', [
            'name' => 'Stripe',
            'code' => 'CARD',
        ]);
        $this->addSql('UPDATE sylius_payment_method SET code = :old WHERE code = :new', [
            'old' => 'STRIPE',
            'new' => 'CARD',
        ]);
    }
}
