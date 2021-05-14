<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210428120402 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fix fulfilled orders with missing sylius_order_vendor entry';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('INSERT INTO sylius_order_vendor (order_id, restaurant_id, items_total, transfer_amount) '
            . 'SELECT o.id AS order_id, v.restaurant_id, o.items_total, 0 AS transfer_amount '
            . 'FROM sylius_order o '
            . 'JOIN vendor v ON o.vendor_id = v.id '
            . 'LEFT JOIN sylius_order_vendor ov ON o.id = ov.order_id '
            . 'WHERE o.state = \'fulfilled\' '
            . 'AND ov.order_id IS NULL '
            . 'AND o.vendor_id IS NOT NULL '
            . 'AND v.restaurant_id IS NOT NULL');
    }

    public function down(Schema $schema): void
    {
    }
}
