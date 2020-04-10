<?php

namespace AppBundle\Utils;

use AppBundle\Sylius\Order\OrderInterface;
use Carbon\Carbon;

class ShippingDateFilter
{
    private $pickupTimeCalculator;

    public function __construct(PickupTimeCalculator $pickupTimeCalculator)
    {
        $this->pickupTimeCalculator = $pickupTimeCalculator;
    }

    /**
     * @param OrderInterface $order
     * @param \DateTime $dropoff
     *
     * @return bool
     */
    public function accept(OrderInterface $order, \DateTime $dropoff, \DateTime $now = null): bool
    {
        if (null === $now) {
            $now = Carbon::now();
        }

        // Obviously, we can't ship in the past
        if ($dropoff <= $now) {
            return false;
        }

        $pickup = $this->pickupTimeCalculator->calculate($order, $dropoff);

        if (!$order->getRestaurant()->isOpen($pickup)) {
            return false;
        }

        if ($pickup <= $now) {
            return false;
        }

        return true;
    }
}
