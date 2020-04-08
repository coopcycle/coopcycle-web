<?php

namespace AppBundle\Utils;

use AppBundle\DataType\TsRange;
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

    private function filterChoices(OrderInterface $cart, array $choices)
    {
        return array_filter($choices, function ($date) use ($cart) {
            return $this->shippingDateFilter->accept($cart, new \DateTime($date));
        });
    }

    /**
     * @see https://stackoverflow.com/questions/4133859/round-up-to-nearest-multiple-of-five-in-php
     */
    private function roundUp($n, $x = 5): int
    {
        $value = (round($n) % $x === 0) ? round($n) : round(($n + $x / 2) / $x) * $x;

        return (int) $value;
    }

    public function getAvailabilities(OrderInterface $cart)
    {
        $hash = spl_object_hash($cart);

        if (!isset($this->choicesCache[$hash])) {

            $restaurant = $cart->getRestaurant();

            $availabilities = $this->filterChoices($cart, $restaurant->getAvailabilities());

            if (empty($availabilities) && 1 === $restaurant->getShippingOptionsDays()) {
                $restaurant->setShippingOptionsDays(2);
                $availabilities = $this->filterChoices($cart, $restaurant->getAvailabilities());
                $restaurant->setShippingOptionsDays(1);
            }

            // FIXME Sort availabilities

            // Make sure to return a zero-indexed array
            $this->choicesCache[$hash] = array_values($availabilities);
        }

        return $this->choicesCache[$hash];
    }

    // FIXME This method should return an object
    public function getTimeInfo(OrderInterface $cart)
    {
        $now = Carbon::now();

        $preparationTime = $this->preparationTimeCalculator
            ->createForRestaurant($cart->getRestaurant())
            ->calculate($cart);

        $shippingTime = $this->shippingTimeCalculator->calculate($cart);

        $asap = $this->getAsap($cart);

        if (null !== $cart->getShippedAt()) {
            $today = $cart->getShippedAt()->format('Y-m-d') === $now->format('Y-m-d');
        } else {
            $today = (new \DateTime($asap))->format('Y-m-d') === $now->format('Y-m-d');
        }

        $diffInMinutes = $now->diffInMinutes(Carbon::parse($asap));

        // We consider it is "fast" if it's less than 45 minutes
        $fast = $diffInMinutes < 45;

        $shippingTimeRange = $this->getShippingTimeRange($cart);

        $lowerDiff = $now->diffInMinutes(Carbon::instance($shippingTimeRange->getLower()));
        $upperDiff = $now->diffInMinutes(Carbon::instance($shippingTimeRange->getUpper()));

        return [
            'preparation' => $preparationTime,
            'shipping' => $shippingTime,
            'asap' => $asap,
            'range' => [
                $shippingTimeRange->getLower()->format(\DateTime::ATOM),
                $shippingTimeRange->getUpper()->format(\DateTime::ATOM),
            ],
            'today' => $today,
            'fast' => $fast,
            'diff' => sprintf('%d - %d',
                $this->roundUp($lowerDiff, 5),
                $this->roundUp($upperDiff, 5)
            ),
        ];
    }

    /**
     * @deprecated
     */
    public function getAsap(OrderInterface $cart)
    {
        $choices = $this->getAvailabilities($cart);

        // TODO Use sort
        return $choices[0];
    }

    public function getShippingTimeRange(OrderInterface $cart): TsRange
    {
        $choices = $this->getAvailabilities($cart);

        // FIXME Throw Exception when there are no choices (empty array)

        $first = new \DateTime($choices[0]);

        $lower = clone $first;
        $upper = clone $first;

        $lower->modify('-5 minutes');
        $upper->modify('+5 minutes');

        $range = new TsRange();
        $range->setLower($lower);
        $range->setUpper($upper);

        return $range;
    }
}
