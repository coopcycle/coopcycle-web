<?php

namespace AppBundle\Utils;

use AppBundle\Entity\GeoCoordinates;

class GeoUtils
{
    public static function asGeoCoordinates($pointAsText)
    {
        preg_match('/POINT\(([0-9\.]+) ([0-9\.]+)\)/', $pointAsText, $matches);

        $latitude = $matches[1];
        $longitude = $matches[2];

        $geo = new GeoCoordinates();
        $geo->setLatitude($latitude);
        $geo->setLongitude($longitude);

        return $geo;
    }
}