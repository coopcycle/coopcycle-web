<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds the pricing rule set chosen by a dispatcher on an order.
 */
final class Version20261008112534 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add pricing_rule_set_id on sylius_order';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_order ADD pricing_rule_set_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE sylius_order ADD CONSTRAINT FK_6196A1F9C213A00E FOREIGN KEY (pricing_rule_set_id) REFERENCES pricing_rule_set (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_6196A1F9C213A00E ON sylius_order (pricing_rule_set_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_order DROP CONSTRAINT FK_6196A1F9C213A00E');
        $this->addSql('DROP INDEX IDX_6196A1F9C213A00E');
        $this->addSql('ALTER TABLE sylius_order DROP pricing_rule_set_id');
    }
}
