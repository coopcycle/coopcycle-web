<?php

namespace AppBundle\Utils;

use AppBundle\DataType\TsRange;
use AppBundle\Entity\TimeSlot;
use AppBundle\Form\Type\AsapChoiceLoader;
use AppBundle\Form\Type\TimeSlotChoiceLoader;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Utils\DateUtils;
use AppBundle\Utils\PreparationTimeCalculator;
use AppBundle\Utils\ShippingDateFilter;
use AppBundle\Utils\ShippingTimeCalculator;
use Carbon\Carbon;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class OrderTimeHelper
{
    private $shippingDateFilter;
    private $preparationTimeCalculator;
    private $shippingTimeCalculator;
    private $country;
    private $choicesCache = [];

    public function __construct(
        ShippingDateFilter $shippingDateFilter,
        PreparationTimeCalculator $preparationTimeCalculator,
        ShippingTimeCalculator $shippingTimeCalculator,
        string $country,
        LoggerInterface $logger = null)
    {
        $this->shippingDateFilter = $shippingDateFilter;
        $this->preparationTimeCalculator = $preparationTimeCalculator;
        $this->shippingTimeCalculator = $shippingTimeCalculator;
        $this->country = $country;
        $this->logger = $logger ?? new NullLogger();
    }

    private function filterChoices(OrderInterface $cart, array $choices)
    {
        return array_filter($choices, function ($date) use ($cart) {

            $result = $this->shippingDateFilter->accept($cart, new \DateTime($date));

            $this->logger->info(sprintf('ShippingDateFilter::accept() returned %s for %s',
                var_export($result, true),
                (new \DateTime($date))->format(\DateTime::ATOM))
            );

            return $result;
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

    private function getChoices(OrderInterface $cart)
    {
        $hash = sprintf('%s-%s', $cart->getFulfillmentMethod(), spl_object_hash($cart));

        if (!isset($this->choicesCache[$hash])) {

            $target = $cart->getTarget();

            $choiceLoader = new AsapChoiceLoader(
                $target->getOpeningHours($cart->getFulfillmentMethod()),
                $target->getClosingRules(),
                $target->getShippingOptionsDays(),
                $target->getOrderingDelayMinutes()
            );

            $choiceList = $choiceLoader->loadChoiceList();
            $values = $this->filterChoices($cart, $choiceList->getValues());

            if (empty($values) && 1 === $target->getShippingOptionsDays()) {

                $choiceLoader->setShippingOptionsDays(2);
                $choiceList = $choiceLoader->loadChoiceList();
                $values = $choiceList->getValues();

                $values = $this->filterChoices($cart, $choiceList->getValues());
            }

            // FIXME Sort availabilities

            // Make sure to return a zero-indexed array
            $this->choicesCache[$hash] = array_values($values);
        }

        return $this->choicesCache[$hash];
    }

    public function getShippingTimeRanges(OrderInterface $cart)
    {
        $target = $cart->getTarget();
        $fulfillmentMethod = $target->getFulfillmentMethod($cart->getFulfillmentMethod());

        $this->logger->info(sprintf('Cart has fulfillment method "%s" and behavior "%s"',
            $fulfillmentMethod->getType(),
            $fulfillmentMethod->getOpeningHoursBehavior()
        ));

        if ($fulfillmentMethod->getOpeningHoursBehavior() === 'time_slot') {

            $ranges = [];

            $choiceLoader = new TimeSlotChoiceLoader(
                TimeSlot::create($target, $fulfillmentMethod),
                $this->country
            );
            $choiceList = $choiceLoader->loadChoiceList();

            foreach ($choiceList->getChoices() as $choice) {
                $ranges[] = $choice->toTsRange();
            }

            return $ranges;
        }

        return array_map(function (string $date) {

            return DateUtils::dateTimeToTsRange(new \DateTime($date), 5);
        }, $this->getChoices($cart));
    }

    /**
     * FIXME This method should return an object
     *
     * @return array
     */
    public function getTimeInfo(OrderInterface $cart)
    {
        $now = Carbon::now();

        $preparationTime = $this->preparationTimeCalculator->calculate($cart);
        $shippingTime = $this->shippingTimeCalculator->calculate($cart);

        $ranges = $this->getShippingTimeRanges($cart);
        $range = $this->getShippingTimeRange($cart);

        $asap = null;
        $fast = false;
        $lowerDiff = $upperDiff = 'N/A';

        if ($range) {
            $lowerDiff = $now->diffInMinutes(Carbon::instance($range->getLower()));
            $upperDiff = $now->diffInMinutes(Carbon::instance($range->getUpper()));

            $lowerDiff = $this->roundUp($lowerDiff, 5);
            $upperDiff = $this->roundUp($upperDiff, 5);

            // We see it as "fast" if it's less than max. 45 minutes
            $fast = $upperDiff <= 45;

            // Legacy
            $asap = Carbon::instance($range->getLower())
                ->average($range->getUpper())
                ->format(\DateTime::ATOM);
        }

        $target = $cart->getTarget();
        $fulfillmentMethod = $target->getFulfillmentMethod($cart->getFulfillmentMethod());

        return [
            'behavior' => $fulfillmentMethod->getOpeningHoursBehavior(),
            'preparation' => $preparationTime,
            'shipping' => $shippingTime,
            'asap' => $asap,
            'range' => $range ? [
                $range->getLower()->format(\DateTime::ATOM),
                $range->getUpper()->format(\DateTime::ATOM),
            ] : null,
            'today' => $range ? DateUtils::isToday($range) : false,
            'fast' => $fast,
            'diff' => sprintf('%d - %d', $lowerDiff, $upperDiff),
            'ranges' => array_map(function (TsRange $range) {
                return [
                    $range->getLower()->format(\DateTime::ATOM),
                    $range->getUpper()->format(\DateTime::ATOM),
                ];
            }, $ranges),
        ];
    }

    /**
     * @return TsRange|null
     */
    public function getShippingTimeRange(OrderInterface $cart): ?TsRange
    {
        $ranges = $this->getShippingTimeRanges($cart);

        if (count($ranges) === 0) {

            return null;
        }

        return current($ranges);
    }
}
