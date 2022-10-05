<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20221005223114 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE sylius_catalog_promotion (id SERIAL NOT NULL, code VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, start_date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, end_date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, enabled BOOLEAN NOT NULL, priority INT DEFAULT 0 NOT NULL, exclusive BOOLEAN DEFAULT \'false\' NOT NULL, state VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1055865077153098 ON sylius_catalog_promotion (code)');
        $this->addSql('CREATE TABLE sylius_catalog_promotion_action (id SERIAL NOT NULL, catalog_promotion_id INT DEFAULT NULL, type VARCHAR(255) NOT NULL, configuration TEXT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_F529624722E2CB5A ON sylius_catalog_promotion_action (catalog_promotion_id)');
        $this->addSql('COMMENT ON COLUMN sylius_catalog_promotion_action.configuration IS \'(DC2Type:array)\'');
        $this->addSql('CREATE TABLE sylius_catalog_promotion_scope (id SERIAL NOT NULL, promotion_id INT DEFAULT NULL, type VARCHAR(255) NOT NULL, configuration TEXT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_584AA86A139DF194 ON sylius_catalog_promotion_scope (promotion_id)');
        $this->addSql('COMMENT ON COLUMN sylius_catalog_promotion_scope.configuration IS \'(DC2Type:array)\'');
        $this->addSql('CREATE TABLE sylius_catalog_promotion_translation (id SERIAL NOT NULL, translatable_id INT NOT NULL, label VARCHAR(255) DEFAULT NULL, description VARCHAR(255) DEFAULT NULL, locale VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_BA065D3C2C2AC5D3 ON sylius_catalog_promotion_translation (translatable_id)');
        $this->addSql('CREATE UNIQUE INDEX sylius_catalog_promotion_translation_uniq_trans ON sylius_catalog_promotion_translation (translatable_id, locale)');
        $this->addSql('ALTER TABLE sylius_catalog_promotion_action ADD CONSTRAINT FK_F529624722E2CB5A FOREIGN KEY (catalog_promotion_id) REFERENCES sylius_catalog_promotion (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sylius_catalog_promotion_scope ADD CONSTRAINT FK_584AA86A139DF194 FOREIGN KEY (promotion_id) REFERENCES sylius_catalog_promotion (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sylius_catalog_promotion_translation ADD CONSTRAINT FK_BA065D3C2C2AC5D3 FOREIGN KEY (translatable_id) REFERENCES sylius_catalog_promotion (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('ALTER TABLE sylius_order_item ADD original_unit_price INT DEFAULT NULL');

        $this->addSql('ALTER TABLE sylius_product_attribute_value DROP CONSTRAINT FK_8A053E54B6E62EFA');
        $this->addSql('ALTER TABLE sylius_product_attribute_value ADD CONSTRAINT FK_8A053E54B6E62EFA FOREIGN KEY (attribute_id) REFERENCES sylius_product_attribute (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('DROP INDEX idx_a29b52373f32dd8');

        $this->addSql('ALTER TABLE sylius_promotion ADD applies_to_discounted BOOLEAN DEFAULT \'true\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_catalog_promotion_action DROP CONSTRAINT FK_F529624722E2CB5A');
        $this->addSql('ALTER TABLE sylius_catalog_promotion_scope DROP CONSTRAINT FK_584AA86A139DF194');
        $this->addSql('ALTER TABLE sylius_catalog_promotion_translation DROP CONSTRAINT FK_BA065D3C2C2AC5D3');
        $this->addSql('DROP TABLE sylius_catalog_promotion');
        $this->addSql('DROP TABLE sylius_catalog_promotion_action');
        $this->addSql('DROP TABLE sylius_catalog_promotion_scope');
        $this->addSql('DROP TABLE sylius_catalog_promotion_translation');

        $this->addSql('ALTER TABLE sylius_order_item DROP original_unit_price');

        $this->addSql('ALTER TABLE sylius_product_attribute_value DROP CONSTRAINT fk_8a053e54b6e62efa');
        $this->addSql('ALTER TABLE sylius_product_attribute_value ADD CONSTRAINT fk_8a053e54b6e62efa FOREIGN KEY (attribute_id) REFERENCES sylius_product_attribute (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('ALTER TABLE sylius_promotion DROP applies_to_discounted');
    }
}
