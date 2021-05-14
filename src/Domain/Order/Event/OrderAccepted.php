<?php

namespace AppBundle\Domain\Order\Event;

use AppBundle\Domain\DomainEvent;
use AppBundle\Domain\HasIconInterface;
use AppBundle\Domain\Order\Event;

class OrderAccepted extends Event implements DomainEvent, HasIconInterface
{
    public static function messageName(): string
    {
        return 'order:accepted';
    }

    public static function iconName()
    {
        return 'thumbs-o-up';
    }
}
