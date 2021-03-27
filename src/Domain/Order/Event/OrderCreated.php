<?php

namespace AppBundle\Domain\Order\Event;

use AppBundle\Domain\DomainEvent;
use AppBundle\Domain\HasIconInterface;
use AppBundle\Domain\Order\Event;

class OrderCreated extends Event implements DomainEvent, HasIconInterface
{
    public static function messageName(): string
    {
        return 'order:created';
    }

    public static function iconName()
    {
        return 'cube';
    }
}
