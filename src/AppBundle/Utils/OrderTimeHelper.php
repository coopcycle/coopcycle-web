<?php

namespace AppBundle\Utils;

use AppBundle\DataType\TsRange;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Utils\DateUtils;
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

    /**
     * FIXME This method should return an object
     *
     * @return array
     */
    public function getTimeInfo(OrderInterface $cart)
    {
        $now = Carbon::now();

        $preparationTime = $this->preparationTimeCalculator
            ->createForRestaurant($cart->getRestaurant())
            ->calculate($cart);

        $shippingTime = $this->shippingTimeCalculator->calculate($cart);

        $shippingTimeRange = $this->getShippingTimeRange($cart);

        $lowerDiff =
            $now->diffInMinutes(Carbon::instance($shippingTimeRange->getLower()));
        $upperDiff =
            $now->diffInMinutes(Carbon::instance($shippingTimeRange->getUpper()));

        $lowerDiff = $this->roundUp($lowerDiff, 5);
        $upperDiff = $this->roundUp($upperDiff, 5);

        // We see it as "fast" if it's less than max. 45 minutes
        $fast = $upperDiff <= 45;

        // Legacy
        $asap = Carbon::instance($shippingTimeRange->getLower())
            ->average($shippingTimeRange->getUpper());

        return [
            'preparation' => $preparationTime,
            'shipping' => $shippingTime,
            'asap' => $asap->format(\DateTime::ATOM),
            'range' => [
                $shippingTimeRange->getLower()->format(\DateTime::ATOM),
                $shippingTimeRange->getUpper()->format(\DateTime::ATOM),
            ],
            'today' => DateUtils::isToday($shippingTimeRange),
            'fast' => $fast,
            'diff' => sprintf('%d - %d', $lowerDiff, $upperDiff)
        ];
    }

    /**
     * @deprecated
     * @return string
     */
    public function getAsap(OrderInterface $cart)
    {
        $shippingTimeRange = $this->getShippingTimeRange($cart);

        return Carbon::instance($shippingTimeRange->getLower())
            ->average($shippingTimeRange->getUpper())
            ->format(\DateTime::ATOM);
    }

    /**
     * @return TsRange
     */
    public function getShippingTimeRange(OrderInterface $cart): TsRange
    {
        $choices = $this->getAvailabilities($cart);

        // FIXME Throw Exception when there are no choices (empty array)

        $first = new \DateTime($choices[0]);

        return DateUtils::dateTimeToTsRange($first, 5);
    }
}
