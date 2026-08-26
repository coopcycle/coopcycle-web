<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260630102748 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop unused Shopify columns from the fulfillment-service approach';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shopify_shop DROP shipping_rate_handle');
        // IF EXISTS on purpose: postal_codes was only ever created by
        // Version20260623100031, which lives on the abandoned `shopify` branch
        // and was never merged. A database that followed master alone — every
        // production instance — never had this column, so a plain DROP aborts
        // the migration. Developers who tracked that branch do have it, and
        // this still cleans it up for them.
        $this->addSql('ALTER TABLE shopify_shop DROP COLUMN IF EXISTS postal_codes');
    }

    public function down(Schema $schema): void
    {
        // Only shipping_rate_handle is restored: rolling back lands on
        // Version20260622140318, whose CREATE TABLE has no postal_codes column,
        // so re-adding it here would invent a column master's schema never had.
        $this->addSql('ALTER TABLE shopify_shop ADD shipping_rate_handle VARCHAR(255) DEFAULT NULL');
    }
}
