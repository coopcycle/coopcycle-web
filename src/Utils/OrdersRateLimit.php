<?php

namespace AppBundle\Utils;

use AppBundle\Domain\Order\Event;
use AppBundle\Domain\Order\Event\OrderCreated;
use AppBundle\Domain\Order\Event\OrderDelayed;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Sylius\Order\OrderInterface;
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
     * @throws \RedisException
     */
    public function isRangeFull(Order|OrderInterface $order, \DateTime $pickupTime): bool {

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

        // Return true if the number of orders exceeds the limit
        $isFull = $count >= $params['max_orders_amount'];

        $logMessage = sprintf(
            "%s: Check if the range is full: %s [Order: %u] [Count: %u] [Pickup: %s] [Params: %s] [Start: %s (%u)] [End: %s (%u)]",
            self::class . ':' . __FUNCTION__,
            $isFull ? 'YES' : 'NO',
            $params['id'],
            $count,
            $pickupTime->format(DATE_W3C),
            json_encode($params),
            (new \DateTime())->setTimestamp($start_time)->format(DATE_W3C),
            $start_time,
            (new \DateTime())->setTimestamp($end_time)->format(DATE_W3C),
            $end_time
        );
        if ($isFull) {
            $this->logger->info($logMessage, ['order' =>  sprintf('#%d', $params['id'])]);
        } else {
            $this->logger->debug($logMessage, ['order' =>  sprintf('#%d', $params['id'])]);
        }
        
        return $isFull;
    }

    /**
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
                $this->handleOrderCreatedEvent($params);
                break;
            case Event\OrderDelayed::class:
                $this->handleOrderDelayedEvent($params);
                break;
            case Event\OrderPicked::class:
            case Event\OrderRefused::class:
            case Event\OrderCancelled::class:
                $this->handleOrderCancel($params);
                break;
            default:
                throw new \Exception(sprintf('%s event is not handled', get_class($event)));

        }
    }


    /**
     * @throws \RedisException
     */
    private function handleOrderCreatedEvent(array $params): void
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
     * @throws \RedisException
     */
    private function handleOrderDelayedEvent(array $params): void
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
     * @throws \RedisException
     */
    private function handleOrderCancel(array $params): void
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
     */
    private function featureEnabled(Order|OrderInterface $order): bool
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
     * @throws \RedisException
     */
    private function garbageCollect(array $params): void
    {
        $now = Carbon::now();

        $this->redis->zRemRangeByScore($params['key'], 0, $now->getTimestamp() - 3600);
    }
}
