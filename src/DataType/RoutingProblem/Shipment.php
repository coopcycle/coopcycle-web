<?php

namespace AppBundle\DataType\RoutingProblem;

use AppBundle\Entity\Delivery;

/**
 * @see https://github.com/VROOM-Project/vroom/blob/master/docs/API.md#shipments
 */
class Shipment
{
    /**
     * a shipment_step object describing pickup
     * @var Job
     */
    public $pickup;

    /**
     * a shipment_step object describing delivery
     * @var Job
     */
    public $delivery;

    public static function fromDelivery(Delivery $delivery)
    {
        $shipment = new self();

        $shipment->pickup = Job::fromTask($delivery->getPickup());
        $shipment->delivery = Job::fromTask($delivery->getDropoff());

        return $shipment;
    }
}
