<?php

namespace AppBundle\Domain\Order\Event;

use AppBundle\Domain\Order\DomainEvent;
use AppBundle\Domain\Order\Event;

class OrderPicked extends Event implements DomainEvent
{
    public static function messageName()
    {
        return 'order:picked';
    }
}
