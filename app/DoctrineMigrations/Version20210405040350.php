<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210405040350 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE sylius_order_vendor (order_id INT NOT NULL, restaurant_id INT NOT NULL, items_total INT NOT NULL, transfer_amount INT NOT NULL, PRIMARY KEY(order_id, restaurant_id))');
        $this->addSql('CREATE INDEX IDX_F26B2BE28D9F6D38 ON sylius_order_vendor (order_id)');
        $this->addSql('CREATE INDEX IDX_F26B2BE2B1E7706E ON sylius_order_vendor (restaurant_id)');
        $this->addSql('ALTER TABLE sylius_order_vendor ADD CONSTRAINT FK_F26B2BE28D9F6D38 FOREIGN KEY (order_id) REFERENCES sylius_order (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE sylius_order_vendor ADD CONSTRAINT FK_F26B2BE2B1E7706E FOREIGN KEY (restaurant_id) REFERENCES restaurant (id) NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('INSERT INTO sylius_order_vendor SELECT o.id AS order_id, rp.restaurant_id, SUM(i.total), COALESCE(a.amount, 0) FROM sylius_order o JOIN sylius_order_item i ON o.id = i.order_id JOIN sylius_product_variant v ON i.variant_id = v.id JOIN sylius_product p ON v.product_id = p.id JOIN restaurant_product rp ON p.id = rp.product_id LEFT JOIN sylius_adjustment a ON a.order_id = o.id AND a.type = \'transfer_amount\' AND a.origin_code::int = rp.restaurant_id  GROUP BY o.id, rp.restaurant_id, a.amount ORDER by o.id');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE sylius_order_vendor');
    }
}
