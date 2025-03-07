<?php

namespace AppBundle\Domain\Order\Workflow;

use AppBundle\Domain\Order\Event;
use AppBundle\Sylius\Order\OrderInterface;

class Guard
{
    public function isFulfillable(OrderInterface $order)
    {
        if ($order->hasVendor() && !$order->isTakeaway()) {

            $delivery = $order->getDelivery();

            if (null === $delivery) {

                return false;
            }

            if ($delivery->getDropoff()->isDone()) {

                return true;
            }

            return false;
        }

        return true;
    }

    public function isRestorable(OrderInterface $order)
    {
        return $order->hasEvent(Event\OrderAccepted::messageName())
            && $order->hasEvent(Event\OrderCancelled::messageName());
    }
}
