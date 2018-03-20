<?php

namespace AppBundle\Entity;

use Sylius\Component\Order\Model\OrderItemInterface;

/**
 * This is a table to associate an OrderItemInterface with a delivery.
 * Once https://github.com/coopcycle/coopcycle-web/issues/155 is fixed, remove this class & properly extend OrderInterface.
 */
class DeliveryOrderItem
{
    protected $orderItem;

    protected $delivery;

    public function __construct(OrderItemInterface $orderItem = null, Delivery $delivery = null)
    {
        $this->orderItem = $orderItem;
        $this->delivery = $delivery;
    }

    public function getOrderItem()
    {
        return $this->orderItem;
    }

    public function getDelivery()
    {
        return $this->delivery;
    }
}
