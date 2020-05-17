<?php

namespace AppBundle\Utils;

use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Utils\TimeRange;
use Carbon\Carbon;

class ShippingDateFilter
{
    private $preparationTimeResolver;
    private $openingHoursCache = [];

    public function __construct(PreparationTimeResolver $preparationTimeResolver)
    {
        $this->preparationTimeResolver = $preparationTimeResolver;
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

        $preparation = $this->preparationTimeResolver->resolve($order, $dropoff);

        if ($preparation <= $now) {
            return false;
        }

        $restaurant = $order->getRestaurant();
        $fulfillmentMethod = $order->getFulfillmentMethod();

        $openingHours = $restaurant->getOpeningHours($fulfillmentMethod);

        if ($restaurant->hasClosingRuleForNow($preparation)) {
            return false;
        }

        if (!$this->isOpen($openingHours, $preparation)) {
            return false;
        }

        return true;
    }

    private function isOpen(array $openingHours, \DateTime $date): bool
    {
        $cacheKey = implode('|', $openingHours);

        if (!isset($this->openingHoursCache[$cacheKey])) {
            $ranges = array_map(function ($oh) {
                return new TimeRange($oh);
            }, $openingHours);
            $this->openingHoursCache[$cacheKey] = $ranges;
        }

        $ohs = $this->openingHoursCache[$cacheKey];

        foreach ($ohs as $oh) {
            if ($oh->isOpen($date)) {

                return true;
            }
        }

        return false;
    }
}
