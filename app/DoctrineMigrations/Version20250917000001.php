<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration to reverse PricingRule <> ProductOptionValue relationship ownership.
 * Makes ProductOptionValue the owning side so that ProductOptionValue can be linked
 * to zero or one PricingRule and a PricingRule can have zero/one/many ProductOptionValue.
 */
final class Version20250917000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Reverse PricingRule <> ProductOptionValue relationship ownership - make ProductOptionValue the owning side';
    }

    public function up(Schema $schema): void
    {
        // Add new pricing_rule_id column to sylius_product_option_value table
        $this->addSql('ALTER TABLE sylius_product_option_value ADD pricing_rule_id INT DEFAULT NULL');

        // Add foreign key constraint
        $this->addSql('ALTER TABLE sylius_product_option_value ADD CONSTRAINT FK_2A41B5E5A5E3B32D FOREIGN KEY (pricing_rule_id) REFERENCES pricing_rule (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

        // Create index for the new foreign key
        $this->addSql('CREATE INDEX IDX_2A41B5E5A5E3B32D ON sylius_product_option_value (pricing_rule_id)');

        // Migrate existing data: copy product_option_value_id relationships to the new structure
        $this->addSql('
            UPDATE sylius_product_option_value
            SET pricing_rule_id = (
                SELECT pr.id
                FROM pricing_rule pr
                WHERE pr.product_option_value_id = sylius_product_option_value.id
                LIMIT 1
            )
            WHERE EXISTS (
                SELECT 1
                FROM pricing_rule pr
                WHERE pr.product_option_value_id = sylius_product_option_value.id
            )
        ');

        // Remove the old foreign key constraint from pricing_rule table
        $this->addSql('ALTER TABLE pricing_rule DROP CONSTRAINT FK_6DCEA672EBDCCF9B');

        // Drop the old index
        $this->addSql('DROP INDEX IDX_6DCEA672EBDCCF9B');

        // Remove the old product_option_value_id column from pricing_rule table
        $this->addSql('ALTER TABLE pricing_rule DROP product_option_value_id');
    }

    public function down(Schema $schema): void
    {
        // Add back the product_option_value_id column to pricing_rule table
        $this->addSql('ALTER TABLE pricing_rule ADD product_option_value_id INT DEFAULT NULL');

        // Migrate data back: copy pricing_rule_id relationships to the old structure
        $this->addSql('
            UPDATE pricing_rule
            SET product_option_value_id = (
                SELECT pov.id
                FROM sylius_product_option_value pov
                WHERE pov.pricing_rule_id = pricing_rule.id
                LIMIT 1
            )
            WHERE EXISTS (
                SELECT 1
                FROM sylius_product_option_value pov
                WHERE pov.pricing_rule_id = pricing_rule.id
            )
        ');

        // Add back the foreign key constraint to pricing_rule table
        $this->addSql('ALTER TABLE pricing_rule ADD CONSTRAINT FK_6DCEA672EBDCCF9B FOREIGN KEY (product_option_value_id) REFERENCES sylius_product_option_value (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

        // Create index for the old foreign key
        $this->addSql('CREATE INDEX IDX_6DCEA672EBDCCF9B ON pricing_rule (product_option_value_id)');

        // Remove the new foreign key constraint from sylius_product_option_value table
        $this->addSql('ALTER TABLE sylius_product_option_value DROP CONSTRAINT FK_2A41B5E5A5E3B32D');

        // Drop the new index
        $this->addSql('DROP INDEX IDX_2A41B5E5A5E3B32D');

        // Remove the new pricing_rule_id column from sylius_product_option_value table
        $this->addSql('ALTER TABLE sylius_product_option_value DROP pricing_rule_id');
    }
}
