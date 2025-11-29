<?php

namespace AppBundle\Message\Order\Command;

use AppBundle\Sylius\Order\OrderInterface;

class Fulfill
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
