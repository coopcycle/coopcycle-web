<?php

namespace AppBundle\Utils;

use AppBundle\Sylius\Order\OrderInterface;

class ShippingDateFilter
{
    private $preparationTimeCalculator;
    private $shippingTimeCalculator;

    public function __construct(
        PreparationTimeCalculator $preparationTimeCalculator,
        ShippingTimeCalculator $shippingTimeCalculator)
    {
        $this->preparationTimeCalculator = $preparationTimeCalculator;
        $this->shippingTimeCalculator = $shippingTimeCalculator;
    }

    public function accept(OrderInterface $order, \DateTime $shippingDate, \DateTime $now = null)
    {
        if (null === $now) {
            $now = new \DateTime();
        }

        // Obviously, we can't ship in the past
        if ($shippingDate <= $now) {
            return false;
        }

        $preparationTime = $this->preparationTimeCalculator->calculate($order);
        $shippingTime = $this->shippingTimeCalculator->calculate($order);

        $shippingDateWithPadding = clone $shippingDate;
        $shippingDateWithPadding->sub(date_interval_create_from_date_string($preparationTime));
        $shippingDateWithPadding->sub(date_interval_create_from_date_string($shippingTime));

        if ($shippingDateWithPadding <= $now) {
            return false;
        }

        return true;
    }
}
