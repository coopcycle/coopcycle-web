<?php

namespace AppBundle\Service;

use AppBundle\Entity\Order;
use AppBundle\Service\DeliveryService\Factory as DeliveryServiceFactory;
use AppBundle\Service\PaymentService;
use Predis\Client as Redis;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Serializer\SerializerInterface;

class OrderManager
{
    private $deliveryServiceFactory;
    private $payment;
    private $redis;
    private $serializer;

    public function __construct(PaymentService $payment, DeliveryServiceFactory $deliveryServiceFactory,
        Redis $redis, SerializerInterface $serializer)
    {
        $this->deliveryServiceFactory = $deliveryServiceFactory;
        $this->payment = $payment;
        $this->redis = $redis;
        $this->serializer = $serializer;
    }

    public function pay(Order $order, $stripeToken)
    {
        $this->payment->authorize($order, $stripeToken);

        $order->setStatus(Order::STATUS_WAITING);

        $channel = sprintf('restaurant:%d:orders', $order->getRestaurant()->getId());
        $this->redis->publish($channel, $this->serializer->serialize($order, 'jsonld'));
    }

    public function accept(Order $order)
    {
        // Order MUST have status = WAITING
        if ($order->getStatus() !== Order::STATUS_WAITING) {
            throw new \Exception(sprintf('Order #%d cannot be accepted anymore', $order->getId()));
        }

        $this->payment->capture($order);

        $order->setStatus(Order::STATUS_ACCEPTED);

        $this->deliveryServiceFactory
            ->createForRestaurant($order->getRestaurant())
            ->create($order);
    }
}
