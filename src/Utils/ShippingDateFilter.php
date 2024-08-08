<?php

namespace AppBundle\Utils;

use AppBundle\DataType\TsRange;
use AppBundle\OpeningHours\SpatieOpeningHoursRegistry;
use AppBundle\Service\LoggingUtils;
use AppBundle\Sylius\Order\OrderInterface;
use Carbon\Carbon;
use Doctrine\Common\Collections\Collection;
use Psr\Log\LoggerInterface;
use Spatie\OpeningHours\OpeningHours;

class ShippingDateFilter
{
    public function __construct(
        private OrderTimelineCalculator $orderTimelineCalculator,
        private OrdersRateLimit         $ordersRateLimit,
        private LoggerInterface         $logger,
        private LoggingUtils $loggingUtils)
    {
    }

    /**
     * @param OrderInterface $order
     * @param TsRange $range
     *
     * @return bool
     * @throws \RedisException
     */
    public function accept(OrderInterface $order, TsRange $range, \DateTime $now = null, int $orderingDelayMinutes = 0): bool
    {
        if (null === $now) {
            $now = Carbon::now();
        }

        $dropoff = $range->getUpper();

        // Obviously, we can't ship in the past
        if ($dropoff <= $now) {

            $this->logger->info(sprintf('ShippingDateFilter::accept | dropoff "%s" is in the past',
                $dropoff->format(\DateTime::ATOM)),
                [
                    'order' => $this->loggingUtils->getOrderId($order),
                    'vendor' => $this->loggingUtils->getVendors($order),
                ]
            );

            return false;
        }

        $timeline = $this->orderTimelineCalculator->calculate($order, $range);
        $preparation = $timeline->getPreparationExpectedAt();
        // if a customer wants a dropoff at 12:00, and preparation time + shipping time is 30 minutes, I need to order at 11:30 at least
        // if the dispatcher adds an delay of 30min
        // then I want here to filter out all of the possible dropoffs between 11:30 and 12:00
        // so I need to substract the delay from the preparation time
        $preparationWithDelay = $preparation->sub(date_interval_create_from_date_string(sprintf('%s minutes', $orderingDelayMinutes)));

        if ($preparationWithDelay <= $now) {

            $this->logger->info(sprintf('ShippingDateFilter::accept | preparation time "%s" with delay "%s" minutes is in the past',
                $preparation->format(\DateTime::ATOM),
                strval($orderingDelayMinutes)),
                [
                    'order' => $this->loggingUtils->getOrderId($order),
                    'vendor' => $this->loggingUtils->getVendors($order),
                ]
            );

            return false;
        }

        $vendorConditions = $order->getVendorConditions();
        $fulfillmentMethod = $order->getFulfillmentMethod();

        if (!$this->isOpen($vendorConditions->getOpeningHours($fulfillmentMethod), $preparationWithDelay, $vendorConditions->getClosingRules())) {

            $this->logger->info(sprintf('ShippingDateFilter::accept | vendor closed at expected preparation time "%s" with delay "%s" minutes',
                $preparation->format(\DateTime::ATOM),
                strval($orderingDelayMinutes)),
                [
                    'order' => $this->loggingUtils->getOrderId($order),
                    'vendor' => $this->loggingUtils->getVendors($order),
                ]
            );

            return false;
        }

        $diffInDays = Carbon::instance($now)->diffInDays(Carbon::instance($dropoff));

        if ($diffInDays >= 7) {

            $this->logger->info(sprintf('ShippingDateFilter::accept | date "%s" is more than 7 days in the future',
                $dropoff->format(\DateTime::ATOM)),
                [
                    'order' => $this->loggingUtils->getOrderId($order),
                    'vendor' => $this->loggingUtils->getVendors($order),
                ]
            );

            return false;
        }

        if ($this->ordersRateLimit->isRangeFull($order, $timeline->getPickupExpectedAt())) {
            return false;
        }

        return true;
    }

    private function isOpen(array $openingHours, \DateTime $date, Collection $closingRules = null): bool
    {
        $oh = SpatieOpeningHoursRegistry::get(
            $openingHours,
            $closingRules
        );

        return $oh->isOpenAt($date);
    }
}
