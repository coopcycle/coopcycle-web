<?php

namespace AppBundle\Utils;

use AppBundle\Domain\Order\Event;
use AppBundle\Domain\Order\Event\OrderCreated;
use AppBundle\Domain\Order\Event\OrderDelayed;
use AppBundle\Entity\Sylius\Order;
use Carbon\Carbon;
use Psr\Log\LoggerInterface;
use Redis;

class OrdersRateLimit
{
    public function __construct(
        private Redis $redis,
        private LoggerInterface $logger
    ) {}


    /**
     * @param Order $order
     * @param \DateTime $pickupTime
     * @return bool
     * @throws \RedisException
     */
    public function isRangeFull(Order $order, \DateTime $pickupTime): bool {

        if (!$this->featureEnabled($order)) {
            return false;
        }

        // Parse order and business parameters
        $params = $this->getOrdersLimitParameters($order);

        // Calculate time window
        $start_time = $pickupTime->getTimestamp();
        $end_time = $start_time + ( $params['max_orders_range_duration'] * 60 );

        // Get all order timestamps within the current window
        $count = $this->redis->zCount($params['key'], $start_time, $end_time);


        //TODO: Conditional logging
        $this->logger->info(sprintf(
            "%s: Check if the range is full [Order: %u] [Count: %u] [Pickup: %s] [Params: %s] [Start: %s (%u)] [End: %s (%u)]",
            self::class . ':' . __FUNCTION__,
            $params['id'],
            $count,
            $pickupTime->format(DATE_W3C),
            json_encode($params),
            (new \DateTime())->setTimestamp($start_time)->format(DATE_W3C),
            $start_time,
            (new \DateTime())->setTimestamp($end_time)->format(DATE_W3C),
            $end_time
        ));

        // Return true if the number of orders exceeds the limit
        return $count >= $params['max_orders_amount'];
    }

    /**
     * @param Event $event
     * @return void
     * @throws \RedisException
     * @throws \Exception
     */
    public function handleEvent(Event $event): void
    {
        if (!$this->featureEnabled($event->getOrder())) {
            return;
        }

        $params = $this->getOrdersLimitParameters($event->getOrder(), true);

        $this->logger->info(sprintf('%s: New event handled [%s]',
            self::class . ':' . __FUNCTION__,  get_class($event)
        ));

        $this->garbageCollect($params);

        switch (get_class($event)) {
            case Event\OrderCreated::class:
                $this->handleOrderCreatedEvent($event, $params);
                break;
            case Event\OrderDelayed::class:
                $this->handleOrderDelayedEvent($event, $params);
                break;
            case Event\OrderPicked::class:
            case Event\OrderRefused::class:
            case Event\OrderCancelled::class:
                $this->handleOrderCancel($event, $params);
                break;
            default:
                throw new \Exception(sprintf('%s event is not handled', get_class($event)));

        }
    }


    /**
     * @param OrderCreated $event
     * @param array $params
     * @return void
     * @throws \RedisException
     */
    private function handleOrderCreatedEvent(Event\OrderCreated $event, array $params): void
    {

        $this->logger->info(sprintf(
            "%s: new order handled [Order: %u] [BusinessID: %u] [Time: %s (%u)]",
            self::class . ':' . __FUNCTION__,
            $params['id'],
            explode(':', $params['key'])[1],
            (new \DateTime())->setTimestamp($params['pickup'])->format(DATE_W3C),
            $params['pickup']
        ));

        $this->redis->zadd($params['key'], $params['pickup'], $params['id']);
    }

    /**
     * @param OrderDelayed $event
     * @param array $params
     * @return void
     * @throws \RedisException
     */
    private function handleOrderDelayedEvent(Event\OrderDelayed $event, array $params): void
    {

        $previous = $this->redis->zScore($params['key'], $params['id']);

        $this->logger->info(sprintf(
            "%s: order delayed [Order: %u] [BusinessID: %u] [Time: %s (%u)] [Diff: %u]",
            self::class . ':' . __FUNCTION__,
            $params['id'],
            explode(':', $params['key'])[1],
            (new \DateTime())->setTimestamp($params['pickup'])->format(DATE_W3C),
            $params['pickup'],
            ($params['pickup'] - $previous)
        ));

        $this->redis->zadd($params['key'], ['GT'], $params['pickup'], $params['id']);
    }

    /**
     * @param Event $event
     * @param array $params
     * @return void
     * @throws \RedisException
     */
    private function handleOrderCancel(Event $event, array $params): void
    {
        $this->logger->info(sprintf(
            "%s: order canceled [Order: %u] [BusinessID: %u]",
            self::class . ':' . __FUNCTION__,
            $params['id'],
            explode(':', $params['key'])[1],
        ));

        $this->redis->zRem($params['key'], $params['id']);

    }


    /**
     * @param Order $order
     * @return bool
     */
    private function featureEnabled(Order $order): bool
    {
        if (!$order->hasVendor()) {
            return false;
        }

        if ($order->isMultiVendor()) {
            return false;
        }

        $localBusiness = $order->getRestaurant();

        return $localBusiness->getRateLimitAmount() &&
            $localBusiness->getRateLimitRangeDuration();
    }

    /**
     * @param Order $order
     * @param bool $timeline
     * @return array
     */
    private function getOrdersLimitParameters(Order $order, bool $timeline = false): array
    {
        $business = $order->getRestaurant();
        $params = [
            'key' => 'rate_limit:' . $business->getId(),
            'id' => $order->getId(),
            'max_orders_amount' => $business->getRateLimitAmount(),
            'max_orders_range_duration' => $business->getRateLimitRangeDuration()
        ];

        if ($timeline) {
            $params['pickup'] = $order->getTimeline()
                ->getPickupExpectedAt()->getTimestamp();
        }

        return $params;
    }

    /**
     * @param array $params
     * @return void
     * @throws \RedisException
     */
    private function garbageCollect(array $params): void
    {
        $now = Carbon::now();

        $this->redis->zRemRangeByScore($params['key'], 0, $now->getTimestamp() - 3600);
    }
}
