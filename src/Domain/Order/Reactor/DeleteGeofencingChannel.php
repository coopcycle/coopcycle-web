<?php

namespace AppBundle\Domain\Order\Reactor;

use AppBundle\Domain\Order\Event;
use AppBundle\Service\Geofencing;

class DeleteGeofencingChannel
{
    private $geofencing;

    public function __construct(Geofencing $geofencing)
    {
        $this->geofencing = $geofencing;
    }

    public function __invoke(Event $event)
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
