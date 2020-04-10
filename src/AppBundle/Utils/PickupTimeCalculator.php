<?php

namespace AppBundle\Utils;

use AppBundle\Sylius\Order\OrderInterface;
use Carbon\Carbon;
use Predis\Client as Redis;

class PickupTimeCalculator
{
    private $preparationTimeCalculator;
    private $shippingTimeCalculator;
    private $redis;

    public function __construct(
        PreparationTimeCalculator $preparationTimeCalculator,
        ShippingTimeCalculator $shippingTimeCalculator,
        Redis $redis)
    {
        $this->preparationTimeCalculator = $preparationTimeCalculator;
        $this->shippingTimeCalculator = $shippingTimeCalculator;
        $this->redis = $redis;
    }

    /**
     * @param OrderInterface $order
     * @param \DateTime $dropoff
     *
     * @return \DateTime
     */
    public function calculate(OrderInterface $order, \DateTime $dropoff): \DateTime
    {
        $preparationTime = $this->preparationTimeCalculator
            ->createForRestaurant($order->getRestaurant())
            ->calculate($order);

        $shippingTime = $this->shippingTimeCalculator->calculate($order);

        $extraTime = '0 minutes';
        if ($preparationDelay = $this->redis->get('foodtech:preparation_delay')) {
            $extraTime = sprintf('%d minutes', intval($preparationDelay));
        }

        $pickup = clone $dropoff;
        $pickup->sub(date_interval_create_from_date_string($shippingTime));
        $pickup->sub(date_interval_create_from_date_string($preparationTime));
        $pickup->sub(date_interval_create_from_date_string($extraTime));

        return $pickup;
    }
}
