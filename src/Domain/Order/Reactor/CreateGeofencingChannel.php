<?php

namespace AppBundle\Domain\Order\Reactor;

use AppBundle\Domain\Order\Event\OrderPicked;
use AppBundle\Entity\Task;
use Redis;
use Psr\Log\LoggerInterface;

class CreateGeofencingChannel
{
    private $tile38;
    private $doorstepChanNamespace;
    private $fleetKey;

    public function __construct(
        Redis $tile38,
        string $doorstepChanNamespace,
        string $fleetKey)
    {
        $this->tile38 = $tile38;
        $this->doorstepChanNamespace = $doorstepChanNamespace;
        $this->fleetKey = $fleetKey;
    }

    public function __invoke(OrderPicked $event)
    {
        $order = $event->getOrder();

        $dropoff = $order->getDelivery()->getDropoff();

        // if (!$dropoff->isDoorstep()) {
        //     return;
        // }

        $this->tile38->rawCommand(
            'SETCHAN',
            sprintf('%s:dropoff:%d', $this->doorstepChanNamespace, $dropoff->getId()),
            'NEARBY',
            $this->fleetKey,
            'FENCE',
            'DETECT',
            'enter',
            'COMMANDS',
            'set',
            'POINT',
            $dropoff->getAddress()->getGeo()->getLatitude(),
            $dropoff->getAddress()->getGeo()->getLongitude(),
            (string) Task::GEOFENCING_RADIUS
        );
    }
}
