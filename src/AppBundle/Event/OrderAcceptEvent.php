<?php

namespace AppBundle\Event;

use AppBundle\Sylius\Order\OrderInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

class OrderAcceptEvent extends GenericEvent
{
    const NAME = 'order.accept';

    public function __construct(OrderInterface $order)
    {
        parent::__construct($order);
    }

    public function getOrder()
    {
        return $this->getSubject();
    }
}
