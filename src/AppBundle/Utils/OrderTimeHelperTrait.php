<?php

namespace AppBundle\Utils;

use AppBundle\Sylius\Order\OrderInterface;
use Carbon\Carbon;

trait OrderTimeHelperTrait
{
	private function getAvailabilities(OrderInterface $cart)
    {
        $restaurant = $cart->getRestaurant();

        $availabilities = $restaurant->getAvailabilities();

        $availabilities = array_filter($availabilities, function ($date) use ($cart) {
            $shippingDate = new \DateTime($date);

            return $this->shippingDateFilter->accept($cart, $shippingDate);
        });

        // Make sure to return a zero-indexed array
        return array_values($availabilities);
    }

    private function getTimeInfo(OrderInterface $cart, array $availabilities)
    {
        $preparationTime = $this->preparationTimeCalculator
            ->createForRestaurant($cart->getRestaurant())
            ->calculate($cart);

        $shippingTime = $this->shippingTimeCalculator->calculate($cart);

        $asap = $this->getAsap($availabilities);

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

    private function getAsap(array $availabilities)
    {
        // TODO Use sort
        return $availabilities[0];
    }
}
