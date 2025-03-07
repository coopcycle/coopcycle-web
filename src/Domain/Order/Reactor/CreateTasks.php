<?php

namespace AppBundle\Domain\Order\Reactor;

use AppBundle\Domain\Order\Event\OrderAccepted;
use AppBundle\Entity\Delivery;
use AppBundle\Service\DeliveryManager;
use AppBundle\Utils\DateUtils;
use AppBundle\Utils\OrderTextEncoder;

class CreateTasks
{
    private $deliveryManager;
    private $orderTextEncoder;

    public function __construct(
        DeliveryManager $deliveryManager,
        OrderTextEncoder $orderTextEncoder)
    {
        $this->deliveryManager = $deliveryManager;
        $this->orderTextEncoder = $orderTextEncoder;
    }

    public function __invoke(OrderAccepted $event)
    {
        $order = $event->getOrder();

        if (null !== $order->getDelivery()) {
            return;
        }

        if ($order->isTakeaway()) {
            return;
        }

        $delivery = $this->deliveryManager->createFromOrder($order);

        $orderAsText = $this->orderTextEncoder->encode($order, 'txt');

        $delivery->getPickup()->setComments($orderAsText);
        $delivery->getPickup()->setMetadata('order_number', $order->getNumber());
        $delivery->getPickup()->setMetadata('payment_method', $order->getPaymentMethod());
        $delivery->getDropoff()->setComments($orderAsText);
        $delivery->getDropoff()->setMetadata('order_number', $order->getNumber());
        $delivery->getDropoff()->setMetadata('payment_method', $order->getPaymentMethod());

        if (!empty($order->getNotes())) {
            $delivery->getPickup()->setMetadata('order_notes', $order->getNotes());
        }

        $order->setDelivery($delivery);
    }
}
