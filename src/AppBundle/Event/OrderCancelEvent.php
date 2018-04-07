<?php

namespace AppBundle\Event;

use AppBundle\Sylius\Order\OrderInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

class OrderCancelEvent extends GenericEvent
{
    const NAME = 'order.cancel';

    public function __construct(OrderInterface $order)
    {
        parent::__construct($order);
    }

    public function getOrder()
    {
        return $this->getSubject();
    }
}
