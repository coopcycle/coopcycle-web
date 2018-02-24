<?php

namespace AppBundle\Event;

use AppBundle\Entity\Order;
use FOS\UserBundle\Model\UserInterface;
use Symfony\Component\EventDispatcher\Event;

class OrderAcceptEvent extends Event
{
    const NAME = 'order.accept';

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
