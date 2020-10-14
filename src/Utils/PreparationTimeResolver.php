<?php

namespace AppBundle\Utils;

use AppBundle\Sylius\Order\OrderInterface;
use Carbon\Carbon;
use Redis;

class PreparationTimeResolver
{
    private $preparationTimeCalculator;
    private $pickupTimeResolver;
    private $redis;
    private $extraTime;
    private $cache = [];

    public function __construct(
        PreparationTimeCalculator $preparationTimeCalculator,
        PickupTimeResolver $pickupTimeResolver,
        Redis $redis)
    {
        $this->preparationTimeCalculator = $preparationTimeCalculator;
        $this->pickupTimeResolver = $pickupTimeResolver;
        $this->redis = $redis;
        $this->extraTime = null;
        $this->cache = [];
    }

    private function getExtraTime()
    {
        if (null === $this->extraTime) {
            $extraTime = '0 minutes';
            if ($preparationDelay = $this->redis->get('foodtech:preparation_delay')) {
                $extraTime = sprintf('%d minutes', intval($preparationDelay));
            }

            $this->extraTime = $extraTime;
        }

        return $this->extraTime;
    }

    /**
     * @param OrderInterface $order
     * @param \DateTime $pickupOrDropoff
     *
     * @return \DateTime
     */
    public function resolve(OrderInterface $order, \DateTime $pickupOrDropoff): \DateTime
    {
        $preparationTime = $this->getPreparationTime($order);
        $extraTime       = $this->getExtraTime();

        $pickup = $this->pickupTimeResolver->resolve($order, $pickupOrDropoff);

        $preparation = clone $pickup;
        $preparation->sub(date_interval_create_from_date_string($preparationTime));
        $preparation->sub(date_interval_create_from_date_string($extraTime));

        return $preparation;
    }

    private function getPreparationTime(OrderInterface $order)
    {
        return $this->preparationTimeCalculator->calculate($order);
    }
}
