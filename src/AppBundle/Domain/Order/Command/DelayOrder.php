<?php

namespace AppBundle\Domain\Order\Command;

use AppBundle\Sylius\Order\OrderInterface;

class DelayOrder
{
    private $order;
    private $delay;

    public function __construct(OrderInterface $order, $delay = 10)
    {
        $this->order = $order;
        $this->delay = $delay;
    }

    public function getOrder()
    {
        return $this->order;
    }

    public function getDelay()
    {
        return $this->delay;
    }
}

