<?php

namespace AppBundle\Event;

use AppBundle\Sylius\Order\OrderInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

class OrderRefuseEvent extends GenericEvent
{
    const NAME = 'order.refuse';

    public function __construct(OrderInterface $order)
    {
        parent::__construct($order);
    }

    public function getOrder()
    {
        return $this->getSubject();
    }
}