<?php

namespace AppBundle\Utils;

use AppBundle\Entity\Address;
use AppBundle\Service\RouteOptimizer;
use AppBundle\Service\RoutingInterface;
use AppBundle\Sylius\Order\OrderInterface;
use Carbon\CarbonInterval;

class ShippingTimeCalculator
{
    public function __construct(
        private RoutingInterface $routing,
        private RouteOptimizer $optimizer,
        private string $fallback = '10 minutes')
    {}

    public function calculate(OrderInterface $order): string
    {
        $pickupAddresses = $order->getPickupAddresses()->toArray();
        $dropoffAddress = $order->getShippingAddress();

        if (null === $dropoffAddress || null === $dropoffAddress->getGeo() || count($pickupAddresses) === 0) {
            return $this->fallback;
        }

        $addresses = $this->optimizer->optimizePickupsAndDelivery($pickupAddresses, $dropoffAddress);

        $coordinates = array_map(fn (Address $a) => $a->getGeo(), $addresses);

        $seconds = $this->routing->getDuration(...$coordinates);

        if (0 === $seconds) {
            return $this->fallback;
        }

        return CarbonInterval::seconds($seconds)->cascade()->forHumans();
    }
}
