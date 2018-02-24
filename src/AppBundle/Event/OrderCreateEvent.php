<?php

namespace AppBundle\Event;

use AppBundle\Entity\Order;
use Symfony\Component\EventDispatcher\Event;

class OrderCreateEvent extends Event
{
    const NAME = 'order.create';

    protected $order;

    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    public function getOrder()
    {
        return $this->order;
    }
}
