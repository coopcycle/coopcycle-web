<?php

namespace AppBundle\Domain\Order\Event;

use AppBundle\Domain\DomainEvent;
use AppBundle\Domain\HasIconInterface;
use AppBundle\Domain\Order\Event;

class OrderDropped extends Event implements DomainEvent, HasIconInterface
{
    public static function messageName(): string
    {
        return 'order:dropped';
    }

    public static function iconName()
    {
        return 'flag-checkered';
    }
}
