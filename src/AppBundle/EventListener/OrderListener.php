<?php

namespace AppBundle\EventListener;

use AppBundle\Entity\Order;
use AppBundle\Service\DeliveryService\Factory as DeliveryServiceFactory;
use Predis\Client as Redis;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\Serializer\SerializerInterface;

class OrderListener
{
    private $deliveryServiceFactory;
    private $redis;
    private $serializer;

    public function __construct(DeliveryServiceFactory $deliveryServiceFactory, Redis $redis, SerializerInterface $serializer)
    {
        $this->deliveryServiceFactory = $deliveryServiceFactory;
        $this->redis = $redis;
        $this->serializer = $serializer;
    }

    private function getDeliveryService(Order $order)
    {
        return $this->deliveryServiceFactory->createForRestaurant($order->getRestaurant());
    }

    /**
     * When an order is successfully paid, actually creates a delivery
     * for the engine configured for the restaurant.
     */
    public function onPaymentSuccess(Event $event)
    {
        $order = $event->getSubject();

        $this->getDeliveryService($order)->create($order);

        // FIXME Not really the good place
        $channel = sprintf('restaurant:%d:orders', $order->getRestaurant()->getId());
        $this->redis->publish($channel, $this->serializer->serialize($order, 'jsonld'));
    }
}
