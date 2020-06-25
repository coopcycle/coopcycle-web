<?php

namespace AppBundle\Utils;

use AppBundle\Sylius\Order\OrderInterface;

class PickupTimeResolver
{
    private $shippingTimeCalculator;

    public function __construct(ShippingTimeCalculator $shippingTimeCalculator)
    {
        $this->shippingTimeCalculator = $shippingTimeCalculator;
    }

    /**
     * @param OrderInterface $order
     * @param \DateTime $pickupOrDropoff
     *
     * @return \DateTime
     */
    public function resolve(OrderInterface $order, \DateTime $pickupOrDropoff): \DateTime
    {
        if ($order->isTakeaway()) {

            return $pickupOrDropoff;
        }

        $pickup = clone $pickupOrDropoff;
        $pickup->sub(
            date_interval_create_from_date_string(
                $this->shippingTimeCalculator->calculate($order)
            )
        );

        return $pickup;
    }
}
