<?php

namespace AppBundle\Message\Order;

use AppBundle\Sylius\Order\OrderInterface;

class ProcessOrderAfterTaskCancellation
{
    private int $orderId;

    public function __construct(OrderInterface $order)
    {
        $this->orderId = $order->getId();
    }

    public function getOrderId(): int
    {
        return $this->orderId;
    }
}
