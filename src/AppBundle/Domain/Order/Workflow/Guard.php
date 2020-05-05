<?php

namespace AppBundle\Domain\Order\Workflow;

use AppBundle\Sylius\Order\OrderInterface;

class Guard
{
    public function isFulfillable(OrderInterface $order)
    {
        if ($order->isFoodtech()) {

            return false;
        }

        return true;
    }
}
