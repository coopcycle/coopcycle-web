<?php

namespace AppBundle\Utils;

use AppBundle\Sylius\Order\OrderInterface;

class PreparationTimeResolver
{
    private $preparationTimeCalculator;
    private $pickupTimeResolver;

    public function __construct(
        PreparationTimeCalculator $preparationTimeCalculator,
        PickupTimeResolver $pickupTimeResolver)
    {
        $this->preparationTimeCalculator = $preparationTimeCalculator;
        $this->pickupTimeResolver = $pickupTimeResolver;
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

        $pickup = $this->pickupTimeResolver->resolve($order, $pickupOrDropoff);

        $preparation = clone $pickup;
        $preparation->sub(date_interval_create_from_date_string($preparationTime));

        return $preparation;
    }

    private function getPreparationTime(OrderInterface $order)
    {
        return $this->preparationTimeCalculator->calculate($order);
    }
}
