<?php

namespace AppBundle\Service;

use AppBundle\Entity\Task;
use Redis;

class Geofencing
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

    public function createChannel(Task $dropoff)
    {
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

    public function deleteChannel(Task $dropoff)
    {
        $this->tile38->rawCommand(
            'DELCHAN',
            sprintf('%s:dropoff:%d', $this->doorstepChanNamespace, $dropoff->getId())
        );
    }
}
