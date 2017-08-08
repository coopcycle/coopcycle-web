<?php

namespace AppBundle\EventListener;

use AppBundle\Entity\Order;
use AppBundle\Service\DeliveryService\Factory as DeliveryServiceFactory;
use Symfony\Component\EventDispatcher\Event;

class OrderListener
{
    private $deliveryServiceFactory;

    public function __construct(DeliveryServiceFactory $deliveryServiceFactory)
    {
        $this->deliveryServiceFactory = $deliveryServiceFactory;
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
    }
}
