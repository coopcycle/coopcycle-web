<?php

namespace AppBundle\Service;

use AppBundle\Entity\Base\GeoCoordinates;

interface RoutingInterface
{
    public function getRawResponse(GeoCoordinates $origin, GeoCoordinates $destination);

    public function getPolyline(GeoCoordinates $origin, GeoCoordinates $destination);

    public function getPoints(GeoCoordinates $origin, GeoCoordinates $destination);

    public function getDistance(GeoCoordinates $origin, GeoCoordinates $destination);

    public function getDuration(GeoCoordinates $origin, GeoCoordinates $destination);
}
