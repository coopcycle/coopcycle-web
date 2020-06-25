<?php

namespace AppBundle\Domain\Order\Command;

use AppBundle\Sylius\Order\OrderInterface;

class AcceptOrder
{
    private $order;

    public function __construct(OrderInterface $order)
    {
        $this->order = $order;
    }

    public function getOrder()
    {
        return $this->order;
    }
}
