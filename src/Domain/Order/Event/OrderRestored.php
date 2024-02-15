<?php

namespace AppBundle\Domain\Order\Event;

use AppBundle\Domain\DomainEvent;
use AppBundle\Domain\HasIconInterface;
use AppBundle\Domain\Order\Event;

class OrderRestored extends Event implements DomainEvent, HasIconInterface
{
    public static function messageName(): string
    {
        return 'order:restored';
    }

    public static function iconName()
    {
        return 'undo';
    }
}
