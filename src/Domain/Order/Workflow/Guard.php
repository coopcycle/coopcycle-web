<?php

namespace AppBundle\Domain\Order\Workflow;

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
}
