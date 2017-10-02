<?php

namespace AppBundle\Service\DeliveryService;

use AppBundle\Entity\Order;
use AppBundle\Service\RoutingInterface;
use Predis\Client as Redis;

class Core extends Base
{
    private $redis;

    public function __construct(RoutingInterface $routing, Redis $redis)
    {
        parent::__construct($routing);

        $this->redis = $redis;
    }

    public function getKey()
    {
        return 'core';
    }

    public function create(Order $order)
    {
        $originAddress = $order->getDelivery()->getOriginAddress();
        $deliveryAddress = $order->getDelivery()->getDeliveryAddress();

        $this->redis->geoadd(
            'deliveries:geo',
            $originAddress->getGeo()->getLongitude(),
            $originAddress->getGeo()->getLatitude(),
            'delivery:'.$order->getDelivery()->getId()
        );

        $this->redis->geoadd(
            'restaurants:geo',
            $originAddress->getGeo()->getLongitude(),
            $originAddress->getGeo()->getLatitude(),
            'delivery:'.$order->getDelivery()->getId()
        );
        $this->redis->geoadd(
            'delivery_addresses:geo',
            $deliveryAddress->getGeo()->getLongitude(),
            $deliveryAddress->getGeo()->getLatitude(),
            'delivery:'.$order->getDelivery()->getId()
        );

        $this->redis->lpush(
            'deliveries:waiting',
            $order->getDelivery()->getId()
        );
    }
}
