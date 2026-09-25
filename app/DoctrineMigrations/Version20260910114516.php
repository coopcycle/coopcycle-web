<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds SEPA Direct Debit support (Stripe Customer + mandate) to the
 * "restaurant" table, so restaurants can also be charged via SEPA, e.g. to
 * claim back the platform fee on meal-voucher-paid orders.
 */
final class Version20260910114516 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add SEPA Direct Debit fields to restaurant';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE restaurant ADD stripe_customer_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE restaurant ADD sepa_payment_method_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE restaurant ADD sepa_mandate_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE restaurant ADD sepa_mandate_status VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE restaurant DROP stripe_customer_id');
        $this->addSql('ALTER TABLE restaurant DROP sepa_payment_method_id');
        $this->addSql('ALTER TABLE restaurant DROP sepa_mandate_id');
        $this->addSql('ALTER TABLE restaurant DROP sepa_mandate_status');
    }
}
