<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231025114558 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pricing_rule_set ADD options JSON DEFAULT NULL');
        $this->addSql('UPDATE pricing_rule_set SET options = \'[]\'');
        $this->addSql('COMMENT ON COLUMN pricing_rule_set.options IS \'(DC2Type:json_array)\'');
        $this->addSql('ALTER TABLE pricing_rule_set ALTER options SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pricing_rule_set DROP options');
    }
}
