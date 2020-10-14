<?php

namespace AppBundle\Utils;

use AppBundle\Service\RoutingInterface;
use AppBundle\Sylius\Order\OrderInterface;
use Carbon\Carbon;

class ShippingTimeCalculator
{
    private $routing;
    private $fallback;

    public function __construct(RoutingInterface $routing, $fallback = '10 minutes')
    {
        $this->routing = $routing;
        $this->fallback = $fallback;
    }

    public function calculate(OrderInterface $order)
    {
        $pickupAddress = $order->getTarget()->getAddress();
        $dropoffAddress = $order->getShippingAddress();

        if (null === $dropoffAddress || null === $dropoffAddress->getGeo()) {
            return $this->fallback;
        }

        $seconds = $this->routing->getDuration(
            $pickupAddress->getGeo(),
            $dropoffAddress->getGeo()
        );

        $now = Carbon::now();
        $dateWithPadding = Carbon::now()->addSeconds($seconds);

        $hours = $dateWithPadding->diffInHours($now);
        $minutes = $dateWithPadding->diffInMinutes($now);

        if ($hours > 0) {

            return sprintf('%d hours %d minutes', $hours, ($minutes % 60));
        }

        return sprintf('%d minutes', $minutes);
    }
}
