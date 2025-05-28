<?php

namespace AppBundle\MessageHandler\Order;

use AppBundle\Domain\Order\Event;
use AppBundle\Domain\Order\Event\OrderCancelled;
use AppBundle\Domain\Order\Event\OrderDropped;
use AppBundle\Service\Geofencing;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler()]
class DeleteGeofencingChannel
{
    private $geofencing;

    public function __construct(Geofencing $geofencing)
    {
        $this->geofencing = $geofencing;
    }

    public function __invoke(OrderDropped|OrderCancelled $event)
    {
        $order = $event->getOrder();

        $delivery = $order->getDelivery();

        if (null === $delivery) {
            return;
        }

        $dropoff = $delivery->getDropoff();

        $this->geofencing->deleteChannel($dropoff);
    }
}
