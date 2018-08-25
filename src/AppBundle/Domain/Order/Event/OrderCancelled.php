<?php

namespace AppBundle\Domain\Order\Event;

use AppBundle\Domain\DomainEvent;
use AppBundle\Domain\Order\Event;

class OrderCancelled extends Event implements DomainEvent
{
    public static function messageName()
    {
        return 'order:cancelled';
    }
}
