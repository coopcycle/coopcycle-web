<?php

namespace AppBundle\Sylius\Order;

use Sylius\Component\Order\Model\OrderItemInterface as BaseOrderItemInterface;

interface OrderItemInterface extends BaseOrderItemInterface
{
    /**
     * @return int
     */
    public function getTaxTotal(): int;
}
