<?php

namespace AppBundle\Domain\Order\Event;

use AppBundle\Domain\DomainEvent;
use AppBundle\Domain\HasIconInterface;
use AppBundle\Domain\Order\Event;

/**
 * An event that occurs when a user accepts an order.
 * IMPORTANT: This event is dispatched before the order is moved to the "accepted" state.
 * If you want to be notified when the order is in the "accepted" state, listen to OrderStateChanged event instead.
 * @see OrderStateChanged
 */
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
