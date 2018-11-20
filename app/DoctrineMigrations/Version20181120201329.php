<?php declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20181120201329 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs

        $stmt = $this->connection->prepare('SELECT sylius_order_item.id FROM sylius_order_item INNER JOIN sylius_order ON sylius_order_item.order_id = sylius_order.id INNER JOIN sylius_product_variant ON sylius_order_item.variant_id = sylius_product_variant.id INNER JOIN sylius_product ON sylius_product_variant.product_id = sylius_product.id WHERE sylius_order.state = \'cart\' AND sylius_product.deleted_at IS NOT NULL');

        $stmt->execute();
        while ($cartItem = $stmt->fetch()) {
            $this->addSql('DELETE FROM sylius_order_item WHERE id = :order_item_id', [
                'order_item_id' => $cartItem['id'],
            ]);
        }

    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
