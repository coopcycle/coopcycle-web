<?php

namespace AppBundle\Utils;

use AppBundle\Sylius\Order\OrderInterface;
use Carbon\Carbon;

class ShippingDateFilter
{
    private $preparationTimeResolver;

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

        if (!$order->getRestaurant()->isOpen($preparation)) {
            return false;
        }

        if ($preparation <= $now) {
            return false;
        }

        return true;
    }
}
