<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230420014210 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE quote_form_submission ADD pricing_rule_set_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE quote_form_submission ADD CONSTRAINT FK_3C61F917C213A00E FOREIGN KEY (pricing_rule_set_id) REFERENCES pricing_rule_set (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_3C61F917C213A00E ON quote_form_submission (pricing_rule_set_id)');
        $this->addSql('ALTER TABLE restaurant ALTER is_available_for_b2b SET DEFAULT \'false\'');
        $this->addSql('COMMENT ON COLUMN zone.polygon IS \'(DC2Type:geojson)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('CREATE SCHEMA topology');
        $this->addSql('CREATE SCHEMA tiger');
        $this->addSql('CREATE SCHEMA tiger_data');
        $this->addSql('ALTER TABLE restaurant ALTER is_available_for_b2b SET DEFAULT \'false\'');
        $this->addSql('COMMENT ON COLUMN zone.polygon IS \'(DC2Type:geojson)(DC2Type:geojson)\'');
        $this->addSql('ALTER TABLE quote_form_submission DROP CONSTRAINT FK_3C61F917C213A00E');
        $this->addSql('DROP INDEX IDX_3C61F917C213A00E');
        $this->addSql('ALTER TABLE quote_form_submission DROP pricing_rule_set_id');
    }
}
