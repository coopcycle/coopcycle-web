<?php

namespace AppBundle\Service\DeliveryService;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Order;
use AppBundle\Service\DeliveryServiceInterface;
use Predis\Client as Redis;

abstract class Base implements DeliveryServiceInterface
{
    protected $redis;
    protected $osrmHost;

    public function __construct(Redis $redis, $osrmHost)
    {
        $this->redis = $redis;
        $this->osrmHost = $osrmHost;
    }

    public function calculate(Delivery $delivery)
    {
        $originLng = $delivery->getOriginAddress()->getGeo()->getLongitude();
        $originLat = $delivery->getOriginAddress()->getGeo()->getLatitude();

        $deliveryLng = $delivery->getDeliveryAddress()->getGeo()->getLongitude();
        $deliveryLat = $delivery->getDeliveryAddress()->getGeo()->getLatitude();

        $response = file_get_contents("http://{$this->osrmHost}/route/v1/bicycle/{$originLng},{$originLat};{$deliveryLng},{$deliveryLat}?overview=full");
        $data = json_decode($response, true);

        $distance = $data['routes'][0]['distance'];
        $duration = $data['routes'][0]['duration'];

        $delivery->setDistance((int) $distance);
        $delivery->setDuration((int) $duration);
    }

    public function onOrderUpdate(Order $order)
    {
        $this->redis->publish('order_events', json_encode([
            'order' => $order->getId(),
            'courier' => null !== $order->getCourier() ? $order->getCourier()->getId() : null,
            'status' => $order->getStatus(),
            'timestamp' => (new \DateTime())->getTimestamp(),
        ]));
    }
}
