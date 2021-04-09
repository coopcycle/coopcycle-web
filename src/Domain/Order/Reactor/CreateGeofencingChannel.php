<?php

namespace AppBundle\Domain\Order\Reactor;

use AppBundle\Domain\Order\Event\OrderPicked;
use AppBundle\Entity\Task;
use AppBundle\Service\Geofencing;

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
