<?php

namespace AppBundle\Service\Routing;

use AppBundle\Service\RoutingInterface;
use AppBundle\Entity\Base\GeoCoordinates;
use Polyline;

abstract class Base implements RoutingInterface
{
    public function getPoints(GeoCoordinates $origin, GeoCoordinates $destination)
    {
        $polyline = $this->getPolyline($origin, $destination);
        $points = Polyline::decode($polyline);

        return Polyline::pair($points);
    }
}
