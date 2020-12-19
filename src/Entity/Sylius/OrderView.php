<?php

namespace AppBundle\Entity\Sylius;

use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Vendor;
use Carbon\Carbon;
use Doctrine\DBAL\Connection;
use AppBundle\Sylius\Order\AdjustmentInterface;

class OrderView
{
    public $id;
    public $number;
    public $fulfillmentMethod;
    public $adjustments = [];
    public $shippingTimeRange;

    public $vendor;
    public $vendorType;
    public $vendorName;

    public $restaurant;
    public $restaurantObj;

    public $total;
    public $itemsTotal;

    private $itemsTaxTotal;

    private $adjustmentsTotalCache = [];
    private $adjustmentsTotalRecursivelyCache = [];

    public function getId()
    {
        return $this->id;
    }

    public function getNumber()
    {
        return $this->number;
    }

    public function getFulfillmentMethod()
    {
        return $this->fulfillmentMethod;
    }

    public function getShippedAt(): ?\DateTime
    {
        return Carbon::instance($this->shippingTimeRange->getLower())
            ->average($this->shippingTimeRange->getUpper());
    }

    public function getItemsTotal(): int
    {
        return $this->itemsTotal;
    }

    public function getItemsTaxTotal(): int
    {
        if (null === $this->itemsTaxTotal) {
            $taxAdjustments = array_filter($this->adjustments, fn($adjustment) => $adjustment['type'] === 'tax');
            $this->itemsTaxTotal = array_reduce($taxAdjustments, function ($accumulator, $adjustment) {

                if ($adjustment['order_item_id'] === null) {
                    return $accumulator;
                }

                return $accumulator + $adjustment['amount'];

            }, 0);
        }

        return $this->itemsTaxTotal;
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function getAdjustmentsTotal(?string $type = null): int
    {
        if (!isset($this->adjustmentsTotalCache[$type])) {

            $total = 0;
            foreach ($this->adjustments as $adjustment) {
                if (null !== $adjustment['order_id'] && $adjustment['type'] === $type) {
                    $total += $adjustment['amount'];
                }
            }

            $this->adjustmentsTotalCache[$type] = $total;
        }

        return $this->adjustmentsTotalCache[$type];
    }

    public function getAdjustmentsTotalRecursively(?string $type = null): int
    {
        if (!isset($this->adjustmentsTotalRecursivelyCache[$type])) {

            $total = $this->getAdjustmentsTotal($type);

            foreach ($this->adjustments as $adjustment) {
                if (null !== $adjustment['order_item_id'] && $adjustment['type'] === $type) {
                    $total += $adjustment['amount'];
                }
            }

            $this->adjustmentsTotalRecursivelyCache[$type] = $total;
        }

        return $this->adjustmentsTotalRecursivelyCache[$type];
    }

    public function getStripeFeeTotal(): int
    {
        return $this->getAdjustmentsTotal(AdjustmentInterface::STRIPE_FEE_ADJUSTMENT);
    }

    public function getFeeTotal(): int
    {
        return $this->getAdjustmentsTotal(AdjustmentInterface::FEE_ADJUSTMENT);
    }

    public function getRevenue(): int
    {
        if ('hub' === $this->vendorType) {

            foreach ($this->adjustments as $adjustment) {
                if ($adjustment['type'] === AdjustmentInterface::TRANSFER_AMOUNT_ADJUSTMENT && ((int) $adjustment['origin_code']) === $this->restaurant) {
                    return $adjustment['amount'];
                }
            }

            return 0;
        }

        return $this->getTotal() - $this->getFeeTotal() - $this->getStripeFeeTotal();
    }

    public function hasVendor(): bool
    {
        return null !== $this->vendor;
    }

    public static function create(Connection $conn)
    {
        $parts = [];

        // Can be useful to build a view of vendors
        // SELECT v.id, COALESCE(h.name, r.name)
        // FROM vendor v
        // LEFT JOIN hub h ON v.hub_id = h.id
        // LEFT JOIN restaurant r ON v.restaurant_id = r.id

        $selects = [];
        $selects[] = 'o.id';
        $selects[] = 'o.number';
        $selects[] = 'CASE WHEN o.takeaway THEN \'collection\' ELSE \'delivery\' END AS fulfillment_method';
        $selects[] = 'v.id AS vendor_id';
        $selects[] = 'CASE WHEN v.hub_id IS NOT NULL THEN \'hub\' WHEN v.restaurant_id IS NOT NULL THEN \'restaurant\' ELSE \'none\' END AS vendor_type';
        $selects[] = 'COALESCE(h.name, r.name) AS vendor_name';
        $selects[] = 'COALESCE(v.restaurant_id, hr.restaurant_id) AS restaurant_id';
        $selects[] = 'o.items_total';
        $selects[] = 'o.total';
        $selects[] = 'o.shipping_time_range';

        // This will load all the adjustments as a JSON array
        // FIXME Adjustments are duplicated when there are multiple rows
        // $selects[] = 'JSON_AGG(ROW_TO_JSON(a)) AS adjustments';

        $parts[] = sprintf('SELECT %s', implode(', ', $selects));

        $parts[] = 'FROM sylius_order o';
        $parts[] = 'INNER JOIN sylius_order_item i ON (o.id = i.order_id)';
        $parts[] = 'INNER JOIN sylius_product_variant va ON (va.id = i.variant_id)';
        $parts[] = 'INNER JOIN sylius_product p ON (p.id = va.product_id)';
        $parts[] = 'LEFT JOIN vendor v ON (o.vendor_id = v.id)';
        $parts[] = 'LEFT JOIN hub h ON (v.hub_id = h.id)';
        $parts[] = 'LEFT JOIN hub_restaurant hr ON (hr.hub_id = h.id)';
        $parts[] = 'LEFT JOIN restaurant r ON (v.restaurant_id = r.id)';
        $parts[] = 'LEFT JOIN restaurant_product rp ON (rp.product_id = p.id AND rp.restaurant_id = COALESCE(v.restaurant_id, hr.restaurant_id))';
        // $parts[] = 'INNER JOIN sylius_adjustment a ON (a.order_id = o.id OR a.order_item_id = i.id)';
        $parts[] = 'WHERE o.state = \'fulfilled\'';
        // This allows to
        // - retrieve orders without vendors
        // - filter out restaurants without items for hub orders
        $parts[] = 'AND (o.vendor_id IS NULL OR rp.product_id IS NOT NULL)';
        $parts[] = 'GROUP BY o.id, o.number, v.id, h.id, COALESCE(v.restaurant_id, hr.restaurant_id), h.name, r.name';
        // $parts[] = 'HAVING (v.hub_id is null OR (v.hub_id IS not null AND a1.amount is not null))';

        $sql = implode(' ', $parts);

        $conn->executeQuery('DROP VIEW IF EXISTS view_restaurant_order');
        $conn->executeQuery(sprintf('CREATE VIEW view_restaurant_order AS %s', $sql));
    }
}
