<?php

namespace AppBundle\Entity\Sylius;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Sylius\Order;
use Carbon\Carbon;
use Doctrine\DBAL\Connection;
use AppBundle\Sylius\Order\AdjustmentInterface;

class OrderView
{
    private $restaurant;

    public $id;
    public $number;
    public $takeaway;

    public $shippingTimeRange;
    public $total;
    public $itemsTotal;

    public $adjustments = [];
    public $vendors = [];

    private $itemsTaxTotal;
    private $adjustmentsTotalCache = [];
    private $adjustmentsTotalRecursivelyCache = [];

    public function __construct(?LocalBusiness $restaurant = null)
    {
        $this->restaurant = $restaurant;
    }

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
        return $this->takeaway ? 'collection' : 'delivery';
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
                if (null !== $adjustment['order_id'] && null === $adjustment['order_item_id'] && $adjustment['type'] === $type) {
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
        if ($this->isMultiVendor() && null !== $this->restaurant) {
            foreach ($this->vendors as $vendor) {
                if ($vendor['restaurant_id'] === $this->restaurant->getId()) {
                    return $vendor['transferAmount'];
                }
            }
        }

        return $this->getTotal() - $this->getFeeTotal() - $this->getStripeFeeTotal();
    }

    public function hasVendor(): bool
    {
        return count($this->vendors) > 0;
    }

    public function isMultiVendor(): bool
    {
        if (!$this->hasVendor()) {

            return false;
        }

        return count($this->vendors) > 1;
    }

    public function getVendorName(): string
    {
        if (!$this->hasVendor()) {

            return '';
        }

        if ($this->isMultiVendor()) {

            return $this->vendors[0]['hub_name'];
        }

        return $this->vendors[0]['restaurant_name'];
    }

    public static function create(array $data, ?LocalBusiness $restaurant = null): self
    {
        $order = new self($restaurant);

        $order->id                = $data['id'];
        $order->number            = $data['number'];
        $order->shippingTimeRange = $data['shippingTimeRange'];
        $order->takeaway          = $data['takeaway'];
        $order->itemsTotal        = $data['itemsTotal'];
        $order->total             = $data['total'];

        return $order;
    }
}
