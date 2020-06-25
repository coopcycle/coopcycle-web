<?php

namespace AppBundle\Utils;

use AppBundle\Entity\Base\GeoCoordinates;

class GeoUtils
{
    public static function asGeoCoordinates($pointAsText)
    {
        preg_match('/POINT\((-?[0-9\.]+) (-?[0-9\.]+)\)/', $pointAsText, $matches);

        $longitude = $matches[1];
        $latitude = $matches[2];

        return new GeoCoordinates($latitude, $longitude);
    }

    public static function asPoint(GeoCoordinates $coordinates)
    {
        // WARNING
        // In WKT, POINT(X Y) translates to POINT(longitude latitude)
        // @see https://postgis.net/2013/08/18/tip_lon_lat/

        return "POINT({$coordinates->getLongitude()} {$coordinates->getLatitude()})";
    }
}
