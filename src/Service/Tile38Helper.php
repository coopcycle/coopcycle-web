<?php

namespace AppBundle\Service;

use AppBundle\Entity\Delivery;
use Redis;

class Tile38Helper
{
    public function __construct(private Redis $tile38, private string $fleetKey)
    {}

    public function getLastLocationByDelivery(Delivery $delivery)
    {
        if (!$delivery->isAssigned()) {
            return null;
        }

        return $this->getLastLocationByUsername(
            $delivery->getPickup()->getAssignedCourier()->getUsername()
        );
    }

    public function getLastLocationByUsername(string $username)
    {
        $result = $this->tile38->rawCommand('GET', $this->fleetKey, $username);

        if (!$result) {
            return null;
        }

        return json_decode($result, true);
    }
}
