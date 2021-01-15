<?php declare(strict_types = 1);

namespace Application\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180327134756 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('CREATE TABLE sylius_product (id SERIAL NOT NULL, code VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, enabled BOOLEAN NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_677B9B7477153098 ON sylius_product (code)');
        $this->addSql('CREATE TABLE sylius_product_options (product_id INT NOT NULL, option_id INT NOT NULL, PRIMARY KEY(product_id, option_id))');
        $this->addSql('CREATE INDEX IDX_2B5FF0094584665A ON sylius_product_options (product_id)');
        $this->addSql('CREATE INDEX IDX_2B5FF009A7C41D6F ON sylius_product_options (option_id)');
        $this->addSql('CREATE TABLE sylius_locale (id SERIAL NOT NULL, code VARCHAR(12) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_7BA1286477153098 ON sylius_locale (code)');
        $this->addSql('CREATE TABLE sylius_product_association (id SERIAL NOT NULL, association_type_id INT NOT NULL, product_id INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_48E9CDABB1E1C39 ON sylius_product_association (association_type_id)');
        $this->addSql('CREATE INDEX IDX_48E9CDAB4584665A ON sylius_product_association (product_id)');
        $this->addSql('CREATE UNIQUE INDEX product_association_idx ON sylius_product_association (product_id, association_type_id)');
        $this->addSql('CREATE TABLE sylius_product_association_product (association_id INT NOT NULL, product_id INT NOT NULL, PRIMARY KEY(association_id, product_id))');
        $this->addSql('CREATE INDEX IDX_A427B983EFB9C8A5 ON sylius_product_association_product (association_id)');
        $this->addSql('CREATE INDEX IDX_A427B9834584665A ON sylius_product_association_product (product_id)');
        $this->addSql('CREATE TABLE sylius_product_association_type (id SERIAL NOT NULL, code VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_CCB8914C77153098 ON sylius_product_association_type (code)');
        $this->addSql('CREATE TABLE sylius_product_association_type_translation (id SERIAL NOT NULL, translatable_id INT NOT NULL, name VARCHAR(255) DEFAULT NULL, locale VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_4F618E52C2AC5D3 ON sylius_product_association_type_translation (translatable_id)');
        $this->addSql('CREATE UNIQUE INDEX sylius_product_association_type_translation_uniq_trans ON sylius_product_association_type_translation (translatable_id, locale)');
        $this->addSql('CREATE TABLE sylius_product_attribute (id SERIAL NOT NULL, code VARCHAR(255) NOT NULL, type VARCHAR(255) NOT NULL, storage_type VARCHAR(255) NOT NULL, configuration TEXT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, position INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_BFAF484A77153098 ON sylius_product_attribute (code)');
        $this->addSql('COMMENT ON COLUMN sylius_product_attribute.configuration IS \'(DC2Type:array)\'');
        $this->addSql('CREATE TABLE sylius_product_attribute_translation (id SERIAL NOT NULL, translatable_id INT NOT NULL, name VARCHAR(255) NOT NULL, locale VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_93850EBA2C2AC5D3 ON sylius_product_attribute_translation (translatable_id)');
        $this->addSql('CREATE UNIQUE INDEX sylius_product_attribute_translation_uniq_trans ON sylius_product_attribute_translation (translatable_id, locale)');
        $this->addSql('CREATE TABLE sylius_product_attribute_value (id SERIAL NOT NULL, product_id INT NOT NULL, attribute_id INT NOT NULL, locale_code VARCHAR(255) NOT NULL, text_value TEXT DEFAULT NULL, boolean_value BOOLEAN DEFAULT NULL, integer_value INT DEFAULT NULL, float_value DOUBLE PRECISION DEFAULT NULL, datetime_value TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, date_value DATE DEFAULT NULL, json_value JSON DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_8A053E544584665A ON sylius_product_attribute_value (product_id)');
        $this->addSql('CREATE INDEX IDX_8A053E54B6E62EFA ON sylius_product_attribute_value (attribute_id)');
        $this->addSql('COMMENT ON COLUMN sylius_product_attribute_value.json_value IS \'(DC2Type:json_array)\'');
        $this->addSql('CREATE TABLE sylius_product_option (id SERIAL NOT NULL, code VARCHAR(255) NOT NULL, position INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_E4C0EBEF77153098 ON sylius_product_option (code)');
        $this->addSql('CREATE TABLE sylius_product_option_translation (id SERIAL NOT NULL, translatable_id INT NOT NULL, name VARCHAR(255) NOT NULL, locale VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_CBA491AD2C2AC5D3 ON sylius_product_option_translation (translatable_id)');
        $this->addSql('CREATE UNIQUE INDEX sylius_product_option_translation_uniq_trans ON sylius_product_option_translation (translatable_id, locale)');
        $this->addSql('CREATE TABLE sylius_product_option_value (id SERIAL NOT NULL, option_id INT NOT NULL, code VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_F7FF7D4B77153098 ON sylius_product_option_value (code)');
        $this->addSql('CREATE INDEX IDX_F7FF7D4BA7C41D6F ON sylius_product_option_value (option_id)');
        $this->addSql('CREATE TABLE sylius_product_option_value_translation (id SERIAL NOT NULL, translatable_id INT NOT NULL, value VARCHAR(255) NOT NULL, locale VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_8D4382DC2C2AC5D3 ON sylius_product_option_value_translation (translatable_id)');
        $this->addSql('CREATE UNIQUE INDEX sylius_product_option_value_translation_uniq_trans ON sylius_product_option_value_translation (translatable_id, locale)');
        $this->addSql('CREATE TABLE sylius_product_translation (id SERIAL NOT NULL, translatable_id INT NOT NULL, name VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, meta_keywords VARCHAR(255) DEFAULT NULL, meta_description VARCHAR(255) DEFAULT NULL, locale VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_105A9082C2AC5D3 ON sylius_product_translation (translatable_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_105A9084180C698989D9B62 ON sylius_product_translation (locale, slug)');
        $this->addSql('CREATE UNIQUE INDEX sylius_product_translation_uniq_trans ON sylius_product_translation (translatable_id, locale)');
        $this->addSql('CREATE TABLE sylius_product_variant (id SERIAL NOT NULL, product_id INT NOT NULL, code VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, position INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_A29B52377153098 ON sylius_product_variant (code)');
        $this->addSql('CREATE INDEX IDX_A29B5234584665A ON sylius_product_variant (product_id)');
        $this->addSql('CREATE TABLE sylius_product_variant_option_value (variant_id INT NOT NULL, option_value_id INT NOT NULL, PRIMARY KEY(variant_id, option_value_id))');
        $this->addSql('CREATE INDEX IDX_76CDAFA13B69A9AF ON sylius_product_variant_option_value (variant_id)');
        $this->addSql('CREATE INDEX IDX_76CDAFA1D957CA06 ON sylius_product_variant_option_value (option_value_id)');
        $this->addSql('CREATE TABLE sylius_product_variant_translation (id SERIAL NOT NULL, translatable_id INT NOT NULL, name VARCHAR(255) DEFAULT NULL, locale VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_8DC18EDC2C2AC5D3 ON sylius_product_variant_translation (translatable_id)');
        $this->addSql('CREATE UNIQUE INDEX sylius_product_variant_translation_uniq_trans ON sylius_product_variant_translation (translatable_id, locale)');
        $this->addSql('ALTER TABLE sylius_product_options ADD CONSTRAINT FK_2B5FF0094584665A FOREIGN KEY (product_id) REFERENCES sylius_product (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sylius_product_options ADD CONSTRAINT FK_2B5FF009A7C41D6F FOREIGN KEY (option_id) REFERENCES sylius_product_option (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sylius_product_association ADD CONSTRAINT FK_48E9CDABB1E1C39 FOREIGN KEY (association_type_id) REFERENCES sylius_product_association_type (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sylius_product_association ADD CONSTRAINT FK_48E9CDAB4584665A FOREIGN KEY (product_id) REFERENCES sylius_product (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sylius_product_association_product ADD CONSTRAINT FK_A427B983EFB9C8A5 FOREIGN KEY (association_id) REFERENCES sylius_product_association (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sylius_product_association_product ADD CONSTRAINT FK_A427B9834584665A FOREIGN KEY (product_id) REFERENCES sylius_product (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sylius_product_association_type_translation ADD CONSTRAINT FK_4F618E52C2AC5D3 FOREIGN KEY (translatable_id) REFERENCES sylius_product_association_type (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sylius_product_attribute_translation ADD CONSTRAINT FK_93850EBA2C2AC5D3 FOREIGN KEY (translatable_id) REFERENCES sylius_product_attribute (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sylius_product_attribute_value ADD CONSTRAINT FK_8A053E544584665A FOREIGN KEY (product_id) REFERENCES sylius_product (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sylius_product_attribute_value ADD CONSTRAINT FK_8A053E54B6E62EFA FOREIGN KEY (attribute_id) REFERENCES sylius_product_attribute (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sylius_product_option_translation ADD CONSTRAINT FK_CBA491AD2C2AC5D3 FOREIGN KEY (translatable_id) REFERENCES sylius_product_option (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sylius_product_option_value ADD CONSTRAINT FK_F7FF7D4BA7C41D6F FOREIGN KEY (option_id) REFERENCES sylius_product_option (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sylius_product_option_value_translation ADD CONSTRAINT FK_8D4382DC2C2AC5D3 FOREIGN KEY (translatable_id) REFERENCES sylius_product_option_value (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sylius_product_translation ADD CONSTRAINT FK_105A9082C2AC5D3 FOREIGN KEY (translatable_id) REFERENCES sylius_product (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sylius_product_variant ADD CONSTRAINT FK_A29B5234584665A FOREIGN KEY (product_id) REFERENCES sylius_product (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sylius_product_variant_option_value ADD CONSTRAINT FK_76CDAFA13B69A9AF FOREIGN KEY (variant_id) REFERENCES sylius_product_variant (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sylius_product_variant_option_value ADD CONSTRAINT FK_76CDAFA1D957CA06 FOREIGN KEY (option_value_id) REFERENCES sylius_product_option_value (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sylius_product_variant_translation ADD CONSTRAINT FK_8DC18EDC2C2AC5D3 FOREIGN KEY (translatable_id) REFERENCES sylius_product_variant (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'postgresql', 'Migration can only be executed safely on \'postgresql\'.');

        $this->addSql('ALTER TABLE sylius_product_options DROP CONSTRAINT FK_2B5FF0094584665A');
        $this->addSql('ALTER TABLE sylius_product_association DROP CONSTRAINT FK_48E9CDAB4584665A');
        $this->addSql('ALTER TABLE sylius_product_association_product DROP CONSTRAINT FK_A427B9834584665A');
        $this->addSql('ALTER TABLE sylius_product_attribute_value DROP CONSTRAINT FK_8A053E544584665A');
        $this->addSql('ALTER TABLE sylius_product_translation DROP CONSTRAINT FK_105A9082C2AC5D3');
        $this->addSql('ALTER TABLE sylius_product_variant DROP CONSTRAINT FK_A29B5234584665A');
        $this->addSql('ALTER TABLE sylius_product_association_product DROP CONSTRAINT FK_A427B983EFB9C8A5');
        $this->addSql('ALTER TABLE sylius_product_association DROP CONSTRAINT FK_48E9CDABB1E1C39');
        $this->addSql('ALTER TABLE sylius_product_association_type_translation DROP CONSTRAINT FK_4F618E52C2AC5D3');
        $this->addSql('ALTER TABLE sylius_product_attribute_translation DROP CONSTRAINT FK_93850EBA2C2AC5D3');
        $this->addSql('ALTER TABLE sylius_product_attribute_value DROP CONSTRAINT FK_8A053E54B6E62EFA');
        $this->addSql('ALTER TABLE sylius_product_options DROP CONSTRAINT FK_2B5FF009A7C41D6F');
        $this->addSql('ALTER TABLE sylius_product_option_translation DROP CONSTRAINT FK_CBA491AD2C2AC5D3');
        $this->addSql('ALTER TABLE sylius_product_option_value DROP CONSTRAINT FK_F7FF7D4BA7C41D6F');
        $this->addSql('ALTER TABLE sylius_product_option_value_translation DROP CONSTRAINT FK_8D4382DC2C2AC5D3');
        $this->addSql('ALTER TABLE sylius_product_variant_option_value DROP CONSTRAINT FK_76CDAFA1D957CA06');
        $this->addSql('ALTER TABLE sylius_product_variant_option_value DROP CONSTRAINT FK_76CDAFA13B69A9AF');
        $this->addSql('ALTER TABLE sylius_product_variant_translation DROP CONSTRAINT FK_8DC18EDC2C2AC5D3');

        $this->addSql('DROP TABLE sylius_product');
        $this->addSql('DROP TABLE sylius_product_options');
        $this->addSql('DROP TABLE sylius_locale');
        $this->addSql('DROP TABLE sylius_product_association');
        $this->addSql('DROP TABLE sylius_product_association_product');
        $this->addSql('DROP TABLE sylius_product_association_type');
        $this->addSql('DROP TABLE sylius_product_association_type_translation');
        $this->addSql('DROP TABLE sylius_product_attribute');
        $this->addSql('DROP TABLE sylius_product_attribute_translation');
        $this->addSql('DROP TABLE sylius_product_attribute_value');
        $this->addSql('DROP TABLE sylius_product_option');
        $this->addSql('DROP TABLE sylius_product_option_translation');
        $this->addSql('DROP TABLE sylius_product_option_value');
        $this->addSql('DROP TABLE sylius_product_option_value_translation');
        $this->addSql('DROP TABLE sylius_product_translation');
        $this->addSql('DROP TABLE sylius_product_variant');
        $this->addSql('DROP TABLE sylius_product_variant_option_value');
        $this->addSql('DROP TABLE sylius_product_variant_translation');
    }
}
