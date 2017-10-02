<?php

namespace AppBundle\Service\DeliveryService;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Order;
use AppBundle\Service\DeliveryServiceInterface;
use AppBundle\Service\RoutingInterface;

abstract class Base implements DeliveryServiceInterface
{
    protected $routing;

    public function __construct(RoutingInterface $routing)
    {
        $this->routing = $routing;
    }

    public function calculate(Delivery $delivery)
    {
        $data = $this->routing->getRawResponse(
            $delivery->getOriginAddress()->getGeo(),
            $delivery->getDeliveryAddress()->getGeo()
        );

        $distance = $data['routes'][0]['distance'];
        $duration = $data['routes'][0]['duration'];

        $delivery->setDistance((int) $distance);
        $delivery->setDuration((int) $duration);
    }
}
