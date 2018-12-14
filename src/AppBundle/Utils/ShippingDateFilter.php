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

        $preparationTime = $this->preparationTimeCalculator
            ->createForRestaurant($order->getRestaurant())
            ->calculate($order);
        $shippingTime = $this->shippingTimeCalculator->calculate($order);

        if ($order->getRestaurant()->isOpen($now)) {

            $shippingDateWithPadding = clone $shippingDate;
            $shippingDateWithPadding->sub(date_interval_create_from_date_string($preparationTime));
            $shippingDateWithPadding->sub(date_interval_create_from_date_string($shippingTime));

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
