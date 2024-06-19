<?php

namespace AppBundle\Domain\Order\Event;

use AppBundle\Domain\DomainEvent;
use AppBundle\Domain\HasIconInterface;
use AppBundle\Domain\Order\Event;

class OrderPreparationStarted extends Event implements DomainEvent, HasIconInterface
{
    public static function messageName(): string
    {
        return 'order:preparation_started';
    }

    public static function iconName()
    {
        return 'thumbs-o-up';
    }
}

