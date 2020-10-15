<?php

namespace AppBundle\Utils;

use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Utils\TimeRange;
use Carbon\Carbon;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class ShippingDateFilter
{
    private $preparationTimeResolver;
    private $openingHoursCache = [];

    public function __construct(PreparationTimeResolver $preparationTimeResolver, LoggerInterface $logger = null)
    {
        $this->preparationTimeResolver = $preparationTimeResolver;
        $this->logger = $logger ?? new NullLogger();
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

            $this->logger->info(sprintf('ShippingDateFilter::accept() - date "%s" is in the past',
                $dropoff->format(\DateTime::ATOM))
            );

            return false;
        }

        $preparation = $this->preparationTimeResolver->resolve($order, $dropoff);

        if ($preparation <= $now) {

            $this->logger->info(sprintf('ShippingDateFilter::accept() - preparation time "%s" is in the past',
                $preparation->format(\DateTime::ATOM))
            );

            return false;
        }

        $target = $order->getTarget();
        $fulfillmentMethod = $order->getFulfillmentMethod();

        $openingHours = $target->getOpeningHours($fulfillmentMethod);

        if ($target->hasClosingRuleFor($preparation)) {

            $this->logger->info(sprintf('ShippingDateFilter::accept() - there is a closing rule for "%s"',
                $preparation->format(\DateTime::ATOM))
            );

            return false;
        }

        if (!$this->isOpen($openingHours, $preparation)) {

            $this->logger->info(sprintf('ShippingDateFilter::accept() - closed at "%s"',
                $preparation->format(\DateTime::ATOM))
            );

            return false;
        }

        return true;
    }

    private function isOpen(array $openingHours, \DateTime $date): bool
    {
        $cacheKey = implode('|', $openingHours);

        if (!isset($this->openingHoursCache[$cacheKey])) {
            $ranges = array_map(function ($oh) {
                return TimeRange::create($oh);
            }, $openingHours);
            $this->openingHoursCache[$cacheKey] = $ranges;
        }

        $ohs = $this->openingHoursCache[$cacheKey];

        foreach ($ohs as $oh) {
            if ($oh->isOpen($date)) {

                return true;
            }
        }

        return false;
    }
}
