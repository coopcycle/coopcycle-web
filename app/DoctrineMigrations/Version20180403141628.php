<?php declare(strict_types = 1);

namespace Application\Migrations;

use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Sylius\Order\AdjustmentInterface;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180403141628 extends AbstractMigration
{
    private static function statusToState($status)
    {
        switch ($status) {
            case 'CREATED':
            case 'WAITING':
                return OrderInterface::STATE_NEW;
            case 'ACCEPTED':
                return OrderInterface::STATE_ACCEPTED;
            case 'REFUSED':
                return OrderInterface::STATE_REFUSED;
            case 'READY':
                return OrderInterface::STATE_FULFILLED;
            default:
                return OrderInterface::STATE_CANCELLED;
        }

    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs

        $stmt = [];
        $stmt['order_items'] =
            $this->connection->prepare('SELECT menu_item_id, quantity FROM order_item WHERE order_id = :order_id');
        $stmt['all_orders'] =
            $this->connection->prepare('SELECT order_.id AS order_id, order_.uuid, order_.restaurant_id, order_.customer_id, order_.total_including_tax, order_.total_tax, order_.status, order_.created_at, order_.updated_at, order_.ready_at, delivery.id AS delivery_id, delivery.delivery_address_id, contract.flat_delivery_price FROM order_ JOIN delivery ON order_.id = delivery.order_id JOIN restaurant ON order_.restaurant_id = restaurant.id JOIN contract ON contract.restaurant_id = restaurant.id');

        $stmt['all_orders']->execute();

        while ($row = $stmt['all_orders']->fetch()) {

            $stmt['order_items']->bindParam('order_id', $row['order_id']);
            $stmt['order_items']->execute();

            $this->addSql('INSERT INTO sylius_order (number, state, items_total, adjustments_total, total, created_at, updated_at, customer_id, restaurant_id, shipping_address_id, shipped_at) VALUES (:number, :state, :items_total, :adjustments_total, :total, :created_at, :updated_at, :customer_id, :restaurant_id, :shipping_address_id, :shipped_at)', [
                'number' => $row['uuid'],
                'state' => self::statusToState($row['status']),
                'items_total' => (int) ($row['total_including_tax'] * 100),
                'adjustments_total' => (int) ($row['flat_delivery_price'] * 100),
                'total' => (int) ($row['total_including_tax'] * 100),
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
                'customer_id' => $row['customer_id'],
                'restaurant_id' => $row['restaurant_id'],
                'shipping_address_id' => $row['delivery_address_id'],
                'shipped_at' => $row['ready_at']
            ]);

            $this->addSql('INSERT INTO sylius_adjustment (order_id, type, label, amount, is_neutral, is_locked, created_at, updated_at) SELECT currval(\'sylius_order_id_seq\'), :type, :label, :amount, \'t\', \'f\', :created_at, :updated_at', [
                'type' => AdjustmentInterface::TAX_ADJUSTMENT,
                'label' => 'TVA 20%',
                'amount' => (int) ($row['total_tax'] * 100),
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
            ]);

            while ($orderItem = $stmt['order_items']->fetch()) {
                $this->addSql('INSERT INTO sylius_order_item (order_id, quantity, unit_price, units_total, adjustments_total, total, is_immutable, variant_id) SELECT currval(\'sylius_order_id_seq\'), :quantity, price, price * :quantity, 0, price * :quantity, \'f\', id FROM sylius_product_variant WHERE code = :product_code', [
                    'quantity' => (int) $orderItem['quantity'],
                    'product_code' => sprintf('CPCCL-FDTCH-%d-001', $orderItem['menu_item_id'])
                ]);
            }

            $this->addSql('UPDATE delivery SET sylius_order_id = currval(\'sylius_order_id_seq\') WHERE id = :delivery_id', [
                'delivery_id' => $row['delivery_id']
            ]);
        }
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
