<?php

namespace AppBundle\Utils;

use AppBundle\DataType\TsRange;
use AppBundle\Entity\TimeSlot;
use AppBundle\Form\Type\AsapChoiceLoader;
use AppBundle\Form\Type\TimeSlotChoiceLoader;
use AppBundle\Form\Type\TsRangeChoice;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Utils\DateUtils;
use AppBundle\Utils\PreparationTimeCalculator;
use AppBundle\Utils\ShippingDateFilter;
use AppBundle\Utils\ShippingTimeCalculator;
use Carbon\Carbon;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Redis;

class OrderTimeHelper
{
    private $shippingDateFilter;
    private $preparationTimeCalculator;
    private $shippingTimeCalculator;
    private $country;
    private $choicesCache = [];
    private $extraTime;

    public function __construct(
        ShippingDateFilter $shippingDateFilter,
        PreparationTimeCalculator $preparationTimeCalculator,
        ShippingTimeCalculator $shippingTimeCalculator,
        Redis $redis,
        string $country,
        LoggerInterface $logger = null)
    {
        $this->shippingDateFilter = $shippingDateFilter;
        $this->preparationTimeCalculator = $preparationTimeCalculator;
        $this->shippingTimeCalculator = $shippingTimeCalculator;
        $this->redis = $redis;
        $this->country = $country;
        $this->logger = $logger ?? new NullLogger();
    }

    private function filterChoices(OrderInterface $cart, array $choices)
    {
        return array_filter($choices, function (TsRangeChoice $choice) use ($cart) {

            $result = $this->shippingDateFilter->accept($cart, $choice->toTsRange());

            $this->logger->info(sprintf('ShippingDateFilter::accept() returned %s for %s',
                var_export($result, true),
                (string) $choice
            ));

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

    private function getExtraTime(): int
    {
        if (null === $this->extraTime) {
            $extraTime = 0;
            if ($value = $this->redis->get('foodtech:preparation_delay')) {
                $extraTime = intval($value);
            }

            $this->extraTime = $extraTime;
        }

        return $this->extraTime;
    }

    private function getOrderingDelayMinutes(int $value)
    {
        return $value + $this->getExtraTime();
    }

    private function getChoices(OrderInterface $cart)
    {
        $hash = sprintf('%s-%s', $cart->getFulfillmentMethod(), spl_object_hash($cart));

        if (!isset($this->choicesCache[$hash])) {

            $vendor = $cart->getVendor();
            $fulfillmentMethod = $cart->getFulfillmentMethodObject();

            $choiceLoader = new AsapChoiceLoader(
                $fulfillmentMethod->getOpeningHours(),
                $vendor->getClosingRules(),
                $this->getOrderingDelayMinutes($fulfillmentMethod->getOrderingDelayMinutes()),
                $fulfillmentMethod->getOption('range_duration', 10),
                $fulfillmentMethod->isPreOrderingAllowed()
            );

            $choiceList = $choiceLoader->loadChoiceList();
            $values = $this->filterChoices($cart, $choiceList->getChoices());

            // FIXME Sort availabilities

            // Make sure to return a zero-indexed array
            $this->choicesCache[$hash] = array_values($values);
        }

        return $this->choicesCache[$hash];
    }

    public function getShippingTimeRanges(OrderInterface $cart)
    {
        $fulfillmentMethod = $cart->getFulfillmentMethodObject();

        $this->logger->info(sprintf('Cart has fulfillment method "%s" and behavior "%s"',
            $fulfillmentMethod->getType(),
            $fulfillmentMethod->getOpeningHoursBehavior()
        ));

        if ($fulfillmentMethod->getOpeningHoursBehavior() === 'time_slot') {

            $vendor = $cart->getVendor();

            $choiceLoader = new TimeSlotChoiceLoader(
                TimeSlot::create($fulfillmentMethod, $vendor),
                $this->country,
                $vendor->getClosingRules(),
                new \DateTime('+7 days')
            );
            $choiceList = $choiceLoader->loadChoiceList();

            $ranges = [];
            foreach ($choiceList->getChoices() as $choice) {
                $ranges[] = $choice->toTsRange();
            }

            return $ranges;
        }

        return array_map(function ($choice) {

            return $choice->toTsRange();
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

        $fulfillmentMethod = $cart->getFulfillmentMethodObject();

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
