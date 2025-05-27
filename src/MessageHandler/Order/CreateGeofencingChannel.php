<?php

namespace AppBundle\MessageHandler\Order;

use AppBundle\Domain\Order\Event\OrderPicked;
use AppBundle\Service\Geofencing;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler()]
class CreateGeofencingChannel
{
    private $geofencing;

    public function __construct(Geofencing $geofencing)
    {
        $this->geofencing = $geofencing;
    }

    public function __invoke(OrderPicked $event)
    {
        $order = $event->getOrder();

        $dropoff = $order->getDelivery()->getDropoff();

        // if (!$dropoff->isDoorstep()) {
        //     return;
        // }

        $this->geofencing->createChannel($dropoff);
    }
}
