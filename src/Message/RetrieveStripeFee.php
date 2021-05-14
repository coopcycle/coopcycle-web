<?php

namespace AppBundle\Message;

use Sylius\Component\Order\Model\OrderInterface;

class RetrieveStripeFee
{
    private $orderNumber;

    public function __construct(OrderInterface $order)
    {
        $this->orderNumber = $order->getNumber();
    }

    public function getOrderNumber(): string
    {
        return $this->orderNumber;
    }
}
