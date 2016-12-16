<?php

namespace AppBundle\Service;

use AppBundle\Entity\GeoCoordinates;

interface RoutingInterface
{
    public function getPolyline(GeoCoordinates $origin, GeoCoordinates $destination);

    public function getPoints(GeoCoordinates $origin, GeoCoordinates $destination);
}