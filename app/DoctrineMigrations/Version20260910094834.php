<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds SEPA Direct Debit support: a Stripe Customer + mandate on Store,
 * and payment tracking (Stripe PaymentIntent id + status) on the
 * "invoiced batch" ExportCommand entity.
 */
final class Version20260910094834 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add SEPA Direct Debit fields to store and sylius_export_command';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE store ADD stripe_customer_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE store ADD sepa_payment_method_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE store ADD sepa_mandate_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE store ADD sepa_mandate_status VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE sylius_export_command ADD stripe_payment_intent_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE sylius_export_command ADD payment_status VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE store DROP stripe_customer_id');
        $this->addSql('ALTER TABLE store DROP sepa_payment_method_id');
        $this->addSql('ALTER TABLE store DROP sepa_mandate_id');
        $this->addSql('ALTER TABLE store DROP sepa_mandate_status');
        $this->addSql('ALTER TABLE sylius_export_command DROP stripe_payment_intent_id');
        $this->addSql('ALTER TABLE sylius_export_command DROP payment_status');
    }
}
