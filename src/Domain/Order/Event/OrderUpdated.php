<?php

namespace AppBundle\Domain\Order\Event;

use AppBundle\Domain\DomainEvent;
use AppBundle\Domain\HasIconInterface;
use AppBundle\Domain\Order\Event;

class OrderUpdated extends Event implements DomainEvent
{
    public static function messageName(): string
    {
        return 'order:updated';
    }
}
