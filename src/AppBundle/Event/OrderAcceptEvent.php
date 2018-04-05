<?php

namespace AppBundle\Event;

use AppBundle\Sylius\Order\OrderInterface;
use Symfony\Component\EventDispatcher\Event;

class OrderAcceptEvent extends Event
{
    const NAME = 'order.accept';

    protected $order;

    public function __construct(OrderInterface $order)
    {
        $this->order = $order;
    }

    public function getOrder()
    {
        return $this->order;
    }
}
