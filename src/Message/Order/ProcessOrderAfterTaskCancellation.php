<?php

namespace AppBundle\Message\Order;

use AppBundle\Sylius\Order\OrderInterface;

class ProcessOrderAfterTaskCancellation
{
    private int $orderId;
    private bool $recalculatePrice;

    public function __construct(OrderInterface $order, bool $recalculatePrice = false)
    {
        $this->orderId = $order->getId();
        $this->recalculatePrice = $recalculatePrice;
    }

    public function getOrderId(): int
    {
        return $this->orderId;
    }

    public function shouldRecalculatePrice(): bool
    {
        return $this->recalculatePrice;
    }
}
