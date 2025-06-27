<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250624002649 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add ProductOption relationship to PricingRule';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE pricing_rule ADD product_option_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE pricing_rule ADD CONSTRAINT FK_6DCEA672C964ABE2 FOREIGN KEY (product_option_id) REFERENCES sylius_product_option (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_6DCEA672C964ABE2 ON pricing_rule (product_option_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE pricing_rule DROP CONSTRAINT FK_6DCEA672C964ABE2');
        $this->addSql('DROP INDEX IDX_6DCEA672C964ABE2');
        $this->addSql('ALTER TABLE pricing_rule DROP product_option_id');
    }
}
