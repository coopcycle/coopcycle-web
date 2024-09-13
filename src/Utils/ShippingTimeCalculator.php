<?php

namespace AppBundle\Utils;

use AppBundle\Entity\Address;
use AppBundle\Service\RouteOptimizer;
use AppBundle\Service\RoutingInterface;
use AppBundle\Sylius\Order\OrderInterface;
use Carbon\CarbonInterval;
use Symfony\Contracts\Cache\CacheInterface;

class ShippingTimeCalculator
{
    public function __construct(
        private RoutingInterface $routing,
        private RouteOptimizer $optimizer,
        private CacheInterface $cache,
        private string $fallback = '10 minutes')
    {
    }

    public function calculate(OrderInterface $order): string
    {
        $pickupAddresses = $order->getPickupAddresses()->toArray();
        $dropoffAddress = $order->getShippingAddress();

        if (null === $dropoffAddress || null === $dropoffAddress->getGeo() || count($pickupAddresses) === 0) {
            return $this->fallback;
        }

        $hash = sprintf('%s-%s-%s',
            implode(',', array_map(function ($address) {
                return $address->getId();
            }, $pickupAddresses)),
            $dropoffAddress->getId(),
            spl_object_hash($order));

        return $this->cache->get($hash, function () use ($dropoffAddress, $pickupAddresses, $hash, $order) {
            $addresses = $this->optimizer->optimizePickupsAndDelivery($pickupAddresses, $dropoffAddress);

            $coordinates = array_map(fn(Address $a) => $a->getGeo(), $addresses);

            $seconds = $this->routing->getDuration(...$coordinates);

            if (0 === $seconds) {
                return $this->fallback;
            }

            return CarbonInterval::seconds($seconds)->cascade()->forHumans();
        });
    }
}
