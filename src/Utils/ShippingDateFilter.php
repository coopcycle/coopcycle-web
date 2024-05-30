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
    public function accept(OrderInterface $order, TsRange $range, \DateTime $now = null): bool
    {
        if (null === $now) {
            $now = Carbon::now();
        }

        $dropoff = $range->getUpper();

        // Obviously, we can't ship in the past
        if ($dropoff <= $now) {

            $this->logger->info(sprintf('Order: %s | Vendor: %s | ShippingDateFilter::accept() - date "%s" is in the past',
                $this->loggingUtils->getOrderId($order),
                $this->loggingUtils->getVendors($order),
                $dropoff->format(\DateTime::ATOM))
            );

            return false;
        }

        $timeline = $this->orderTimelineCalculator->calculate($order, $range);
        $preparation = $timeline->getPreparationExpectedAt();

        if ($preparation <= $now) {

            $this->logger->info(sprintf('Order: %s | Vendor: %s | ShippingDateFilter::accept() - preparation time "%s" is in the past',
                $this->loggingUtils->getOrderId($order),
                $this->loggingUtils->getVendors($order),
                $preparation->format(\DateTime::ATOM))
            );

            return false;
        }

        $vendorConditions = $order->getVendorConditions();
        $fulfillmentMethod = $order->getFulfillmentMethod();

        if (!$this->isOpen($vendorConditions->getOpeningHours($fulfillmentMethod), $preparation, $vendorConditions->getClosingRules())) {

            $this->logger->info(sprintf('Order: %s | Vendor: %s | ShippingDateFilter::accept() - closed at "%s"',
                $this->loggingUtils->getOrderId($order),
                $this->loggingUtils->getVendors($order),
                $preparation->format(\DateTime::ATOM))
            );

            return false;
        }

        $diffInDays = Carbon::instance($now)->diffInDays(Carbon::instance($dropoff));

        if ($diffInDays >= 7) {

            $this->logger->info(sprintf('Order: %s | Vendor: %s | ShippingDateFilter::accept() - date "%s" is more than 7 days in the future',
                $this->loggingUtils->getOrderId($order),
                $this->loggingUtils->getVendors($order),
                $dropoff->format(\DateTime::ATOM))
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
