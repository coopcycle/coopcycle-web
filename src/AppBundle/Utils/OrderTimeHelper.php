<?php

namespace AppBundle\Utils;

use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Utils\PreparationTimeCalculator;
use AppBundle\Utils\ShippingDateFilter;
use AppBundle\Utils\ShippingTimeCalculator;
use Carbon\Carbon;

class OrderTimeHelper
{
    private $shippingDateFilter;
    private $preparationTimeCalculator;
    private $shippingTimeCalculator;
    private $choicesCache = [];

    public function __construct(
        ShippingDateFilter $shippingDateFilter,
        PreparationTimeCalculator $preparationTimeCalculator,
        ShippingTimeCalculator $shippingTimeCalculator)
    {
        $this->shippingDateFilter = $shippingDateFilter;
        $this->preparationTimeCalculator = $preparationTimeCalculator;
        $this->shippingTimeCalculator = $shippingTimeCalculator;
    }

    public function getAvailabilities(OrderInterface $cart)
    {
        $hash = spl_object_hash($cart);

        if (!isset($this->choicesCache[$hash])) {

            $restaurant = $cart->getRestaurant();

            $availabilities = $restaurant->getAvailabilities();

            $availabilities = array_filter($availabilities, function ($date) use ($cart) {
                $shippingDate = new \DateTime($date);

                return $this->shippingDateFilter->accept($cart, $shippingDate);
            });

            // Make sure to return a zero-indexed array
            $this->choicesCache[$hash] = array_values($availabilities);
        }

        return $this->choicesCache[$hash];
    }

    public function getTimeInfo(OrderInterface $cart)
    {
        $preparationTime = $this->preparationTimeCalculator
            ->createForRestaurant($cart->getRestaurant())
            ->calculate($cart);

        $shippingTime = $this->shippingTimeCalculator->calculate($cart);

        $asap = $this->getAsap($cart);

        if (null !== $cart->getShippedAt()) {
            $today = $cart->getShippedAt()->format('Y-m-d') === Carbon::now()->format('Y-m-d');
        } else {
            $today = (new \DateTime($asap))->format('Y-m-d') === Carbon::now()->format('Y-m-d');
        }

        $diffInMinutes = Carbon::now()->diffInMinutes(Carbon::parse($asap));

        // We consider it is "fast" if it's less than 45 minutes
        $fast = $diffInMinutes < 45;

        // Round the diff to be a multiple of 5
        if (($diffInMinutes % 5) !== 0) {
            do {
                ++$diffInMinutes;
            } while (($diffInMinutes % 5) !== 0);
        }

        return [
            'preparation' => $preparationTime,
            'shipping' => $shippingTime,
            'asap' => $asap,
            'today' => $today,
            'fast' => $fast,
            'diff' => sprintf('%d - %d', $diffInMinutes, ($diffInMinutes + 5)),
        ];
    }

    public function getAsap(OrderInterface $cart)
    {
        $choices = $this->getAvailabilities($cart);

        // TODO Use sort
        return $choices[0];
    }
}
