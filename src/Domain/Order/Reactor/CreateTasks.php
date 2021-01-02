<?php

namespace AppBundle\Domain\Order\Reactor;

use AppBundle\Domain\Order\Event\OrderAccepted;
use AppBundle\Entity\Delivery;
use AppBundle\Service\DeliveryManager;
use AppBundle\Utils\DateUtils;
use AppBundle\Utils\OrderTextEncoder;
use Carbon\Carbon;

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
        $delivery->getDropoff()->setComments($orderAsText);

        $order->setDelivery($delivery);
    }
}
