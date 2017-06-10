<?php

namespace AppBundle\Service\DeliveryService;

use AppBundle\Entity\Order;

class Core extends Base
{
    public function getKey()
    {
        return 'core';
    }

    public function create(Order $order)
    {
        $originAddress = $order->getDelivery()->getOriginAddress();
        $deliveryAddress = $order->getDelivery()->getDeliveryAddress();

        $this->redis->geoadd(
            'orders:geo',
            $originAddress->getGeo()->getLongitude(),
            $originAddress->getGeo()->getLatitude(),
            'order:'.$order->getId()
        );

        $this->redis->geoadd(
            'restaurants:geo',
            $originAddress->getGeo()->getLongitude(),
            $originAddress->getGeo()->getLatitude(),
            'order:'.$order->getId()
        );
        $this->redis->geoadd(
            'delivery_addresses:geo',
            $deliveryAddress->getGeo()->getLongitude(),
            $deliveryAddress->getGeo()->getLatitude(),
            'order:'.$order->getId()
        );

        $this->redis->lpush(
            'orders:waiting',
            $order->getId()
        );
    }
}
