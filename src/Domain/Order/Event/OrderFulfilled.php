<?php

namespace AppBundle\Domain\Order\Event;

use AppBundle\Domain\DomainEvent;
use AppBundle\Domain\HasIconInterface;
use AppBundle\Domain\Order\Event;
use AppBundle\Sylius\Order\OrderInterface;

class OrderFulfilled extends Event implements DomainEvent, HasIconInterface
{
    public static function messageName()
    {
        return 'order:fulfilled';
    }

    public function __construct(OrderInterface $order)
    {
        parent::__construct($order);
    }

    public static function iconName()
    {
        return 'check';
    }
}

