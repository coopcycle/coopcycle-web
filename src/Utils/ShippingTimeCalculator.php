<?php

namespace AppBundle\Utils;

use AppBundle\Service\RoutingInterface;
use AppBundle\Sylius\Order\OrderInterface;
use Carbon\CarbonInterval;

class ShippingTimeCalculator
{
    private $routing;
    private $fallback;

    public function __construct(RoutingInterface $routing, $fallback = '10 minutes')
    {
        $this->routing = $routing;
        $this->fallback = $fallback;
    }

    public function calculate(OrderInterface $order): string
    {
        $pickupAddress = $order->getPickupAddress();
        $dropoffAddress = $order->getShippingAddress();

        if (null === $dropoffAddress || null === $dropoffAddress->getGeo()) {
            return $this->fallback;
        }

        $seconds = $this->routing->getDuration(
            $pickupAddress->getGeo(),
            $dropoffAddress->getGeo()
        );

        if (0 === $seconds) {
            return $this->fallback;
        }

        return CarbonInterval::seconds($seconds)->cascade()->forHumans();
    }
}
