<?php

namespace AppBundle\Utils;

use AppBundle\Sylius\Order\OrderInterface;
use Carbon\Carbon;
use Predis\Client as Redis;

class ShippingDateFilter
{
    private $redis;
    private $preparationTimeCalculator;
    private $shippingTimeCalculator;

    public function __construct(
        Redis $redis,
        PreparationTimeCalculator $preparationTimeCalculator,
        ShippingTimeCalculator $shippingTimeCalculator)
    {
        $this->redis = $redis;
        $this->preparationTimeCalculator = $preparationTimeCalculator;
        $this->shippingTimeCalculator = $shippingTimeCalculator;
    }

    public function accept(OrderInterface $order, \DateTime $shippingDate, \DateTime $now = null)
    {
        if (null === $now) {
            $now = Carbon::now();
        }

        // Obviously, we can't ship in the past
        if ($shippingDate <= $now) {
            return false;
        }

        $preparationTime = $this->preparationTimeCalculator
            ->createForRestaurant($order->getRestaurant())
            ->calculate($order);
        $shippingTime = $this->shippingTimeCalculator->calculate($order);

        $extraTime = '0 minutes';
        if ($preparationDelay = $this->redis->get('foodtech:preparation_delay')) {
            $extraTime = sprintf('%d minutes', intval($preparationDelay));
        }

        if ($order->getRestaurant()->isOpen($now)) {

            $shippingDateWithPadding = clone $shippingDate;
            $shippingDateWithPadding->sub(date_interval_create_from_date_string($preparationTime));
            $shippingDateWithPadding->sub(date_interval_create_from_date_string($shippingTime));
            $shippingDateWithPadding->sub(date_interval_create_from_date_string($extraTime));

            if ($shippingDateWithPadding <= $now) {
                return false;
            }

        } else {

            $nextOpeningDate = $order->getRestaurant()->getNextOpeningDate($now);

            $nextOpeningDateWithPadding = clone $nextOpeningDate;
            $nextOpeningDateWithPadding->add(date_interval_create_from_date_string($preparationTime));
            $nextOpeningDateWithPadding->add(date_interval_create_from_date_string($shippingTime));

            if ($nextOpeningDateWithPadding >= $shippingDate) {
                return false;
            }
        }

        return true;
    }
}
