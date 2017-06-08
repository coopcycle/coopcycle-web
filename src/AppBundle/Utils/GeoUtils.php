<?php

namespace AppBundle\Utils;

use AppBundle\Entity\Base\GeoCoordinates;

class GeoUtils
{
    public static function asGeoCoordinates($pointAsText)
    {
        preg_match('/POINT\(([0-9\.]+) ([0-9\.]+)\)/', $pointAsText, $matches);

        $latitude = $matches[1];
        $longitude = $matches[2];

        return new GeoCoordinates($latitude, $longitude);
    }

    public static function asPoint(GeoCoordinates $coordinates)
    {
        // SRID=4326;POINT(48.8758246 2.37003870000001)
        return "POINT({$coordinates->getLatitude()} {$coordinates->getLongitude()})";
    }
}
