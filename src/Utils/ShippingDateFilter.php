<?php

namespace AppBundle\Utils;

use AppBundle\DataType\TsRange;
use AppBundle\Fulfillment\FulfillmentMethodResolver;
use AppBundle\OpeningHours\SpatieOpeningHoursRegistry;
use AppBundle\Service\LoggingUtils;
use AppBundle\Sylius\Order\OrderInterface;
use Carbon\Carbon;
use Doctrine\Common\Collections\Collection;
use Psr\Log\LoggerInterface;
use Redis;

class ShippingDateFilter
{
    private $extraTime;

    public function __construct(
        private Redis $redis,
        private OrderTimelineCalculator $orderTimelineCalculator,
        private OrdersRateLimit         $ordersRateLimit,
        private FulfillmentMethodResolver $fulfillmentMethodResolver,
        private LoggerInterface         $logger,
        private LoggingUtils $loggingUtils
    )
    {
    }

    /**
     * @param OrderInterface $order
     * @param TsRange $range
     *
     * @return bool
     * @throws \RedisException
     */
    public function accept(OrderInterface $order, TsRange $range, \DateTime $now = null): bool
    {
        if (null === $now) {
            $now = Carbon::now();
        }

        $dropoff = $range->getUpper();

        // Obviously, we can't ship in the past
        if ($dropoff <= $now) {

            $this->logger->debug(sprintf('ShippingDateFilter::accept | dropoff "%s" is in the past',
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

        $priorNoticeDelay = $this->fulfillmentMethodResolver->resolveForOrder($order)->getOrderingDelayMinutes();

        $preparationCanStartAt = clone $now;
        if ($priorNoticeDelay > 0) {
            $preparationCanStartAt = $preparationCanStartAt->add(date_interval_create_from_date_string(sprintf('%s minutes', $priorNoticeDelay)));
        }

        if ($preparation <= $preparationCanStartAt) {
            $this->logger->debug(sprintf('ShippingDateFilter::accept | preparation time "%s" with prior notice "%s" is in the past',
                $preparation->format(\DateTime::ATOM),
                strval($priorNoticeDelay)),
                [
                    'order' => $this->loggingUtils->getOrderId($order),
                    'vendor' => $this->loggingUtils->getVendors($order),
                ]
            );

            return false;
        }


        $pickup = $timeline->getPickupExpectedAt();
        $dispatchDelayForPickup = $this->getDispatchDelayForPickup();

        $pickupCanStartAt = clone $now;
        if ($dispatchDelayForPickup > 0) {
            $pickupCanStartAt = $pickupCanStartAt->add(date_interval_create_from_date_string(sprintf('%s minutes', $dispatchDelayForPickup)));
        }

        if ($pickup <= $pickupCanStartAt) {

            $this->logger->debug(sprintf('ShippingDateFilter::accept | pickup time "%s" with pickup delay "%s" minutes is in the past',
                $pickup->format(\DateTime::ATOM),
                strval($dispatchDelayForPickup)),
                [
                    'order' => $this->loggingUtils->getOrderId($order),
                    'vendor' => $this->loggingUtils->getVendors($order),
                ]
            );

            return false;
        }

        $vendorConditions = $order->getVendorConditions();
        $fulfillmentMethod = $order->getFulfillmentMethod();

        if (!$this->isOpen($vendorConditions->getOpeningHours($fulfillmentMethod), $preparation, $vendorConditions->getClosingRules())) {

            $this->logger->debug(sprintf('ShippingDateFilter::accept | vendor closed at expected preparation time "%s"',
                $preparation->format(\DateTime::ATOM)),
                [
                    'order' => $this->loggingUtils->getOrderId($order),
                    'vendor' => $this->loggingUtils->getVendors($order),
                ]
            );

            return false;
        }

        $diffInDays = Carbon::instance($now)->diffInDays(Carbon::instance($dropoff));

        if ($diffInDays >= 7) {

            $this->logger->debug(sprintf('ShippingDateFilter::accept | date "%s" is more than 7 days in the future',
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

    private function getDispatchDelayForPickup(): int
    {
        if (null === $this->extraTime) {
            $extraTime = 0;
            if ($value = $this->redis->get('foodtech:dispatch_delay_for_pickup')) {
                $extraTime = intval($value);
            }

            $this->extraTime = $extraTime;
        }

        return $this->extraTime;
    }
}
