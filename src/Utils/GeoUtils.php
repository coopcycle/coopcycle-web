<?php

namespace AppBundle\Utils;

use AppBundle\Entity\Base\GeoCoordinates;
use League\Geotools\Geotools;
use League\Geotools\Coordinate\Coordinate;

class GeoUtils
{
    const RADIUS_KM = 6378.1;

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

    /**
     * @see https://gist.github.com/marcus-at-localhost/39a346e7d7f872187124af9cd582f833
     */
    private static function getBoundingBoxCoords($latitude, $longitude, $bearing, $distance)
    {
        $radius = self::RADIUS_KM;

        //  New latitude in degrees.
        $new_latitude = rad2deg(asin(sin(deg2rad($latitude)) * cos($distance / $radius) + cos(deg2rad($latitude)) * sin($distance / $radius) * cos(deg2rad($bearing))));

        //  New longitude in degrees.
        $new_longitude = rad2deg(deg2rad($longitude) + atan2(sin(deg2rad($bearing)) * sin($distance / $radius) * cos(deg2rad($latitude)), cos($distance / $radius) - sin(deg2rad($latitude)) * sin(deg2rad($new_latitude))));

        return [
            $new_latitude,
            $new_longitude
        ];
    }

    public static function getViewbox($lat, $lng, $distance = 50): array
    {
        $geotools = new Geotools();

        // We generate a bounding box around the center
        // the adresses will be generated inside this bounding box
        $northEast = new Coordinate(self::getBoundingBoxCoords($lat, $lng, 45,  $distance));
        $southEast = new Coordinate(self::getBoundingBoxCoords($lat, $lng, 135, $distance));
        $southWest = new Coordinate(self::getBoundingBoxCoords($lat, $lng, 225, $distance));
        $northWest = new Coordinate(self::getBoundingBoxCoords($lat, $lng, 315, $distance));

        // Long = X, Lat = Y

        $latMin = $northWest->getLatitude();
        $latMax = $southWest->getLatitude();

        $lngMin = $northWest->getLongitude();
        $lngMax = $northEast->getLongitude();

        return [
            $lngMax,
            $latMax,
            $lngMin,
            $latMin
        ];
    }
}
