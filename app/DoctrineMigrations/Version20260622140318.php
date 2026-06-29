<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260622140318 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create tables for Shopify';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE shopify_order (id SERIAL NOT NULL, delivery_id INT DEFAULT NULL, shop_id INT NOT NULL, shopify_order_id VARCHAR(255) NOT NULL, shopify_order_name VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_42167EC412136921 ON shopify_order (delivery_id)');
        $this->addSql('CREATE INDEX IDX_42167EC44D16C4DD ON shopify_order (shop_id)');
        $this->addSql('CREATE TABLE shopify_shop (id SERIAL NOT NULL, store_id INT DEFAULT NULL, shop_domain VARCHAR(255) NOT NULL, access_token VARCHAR(255) NOT NULL, webhook_secret VARCHAR(255) NOT NULL, fulfillment_service_id VARCHAR(255) DEFAULT NULL, shipping_rate_handle VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_2EDB2BDC220B3889 ON shopify_shop (shop_domain)');
        $this->addSql('CREATE INDEX IDX_2EDB2BDCB092A811 ON shopify_shop (store_id)');
        $this->addSql('ALTER TABLE shopify_order ADD CONSTRAINT FK_42167EC412136921 FOREIGN KEY (delivery_id) REFERENCES delivery (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE shopify_order ADD CONSTRAINT FK_42167EC44D16C4DD FOREIGN KEY (shop_id) REFERENCES shopify_shop (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE shopify_shop ADD CONSTRAINT FK_2EDB2BDCB092A811 FOREIGN KEY (store_id) REFERENCES store (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shopify_order DROP CONSTRAINT FK_42167EC412136921');
        $this->addSql('ALTER TABLE shopify_order DROP CONSTRAINT FK_42167EC44D16C4DD');
        $this->addSql('ALTER TABLE shopify_shop DROP CONSTRAINT FK_2EDB2BDCB092A811');
        $this->addSql('DROP TABLE shopify_order');
        $this->addSql('DROP TABLE shopify_shop');
        $this->addSql('COMMENT ON COLUMN city_zone.polygon IS \'(DC2Type:geojson)(DC2Type:geojson)\'');
        $this->addSql('COMMENT ON COLUMN zone.polygon IS \'(DC2Type:geojson)(DC2Type:geojson)\'');
    }
}
