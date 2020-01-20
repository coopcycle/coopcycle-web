<?php

namespace AppBundle\Service\Routing;

use AppBundle\Service\RoutingInterface;
use AppBundle\Entity\Base\GeoCoordinates;
use GuzzleHttp\Client;
use League\Geotools\Geotools;
use League\Geotools\Coordinate\Coordinate;
use Polyline;

class Fallback extends Base
{
    // https://en.wikipedia.org/wiki/Bicycle_performance#Typical_speeds
    const KILOMETERS_PER_HOUR = 15.5;

    /**
     * {@inheritdoc}
     */
    public function getPolyline(GeoCoordinates $origin, GeoCoordinates $destination)
    {
        $points = [
            [ $origin->getLatitude(), $origin->getLongitude() ],
            [ $destination->getLatitude(), $destination->getLongitude() ],
        ];

        return Polyline::encode($points);
    }

    /**
     * {@inheritdoc}
     */
    public function getDistance(GeoCoordinates $origin, GeoCoordinates $destination)
    {
        $geotools = new Geotools();
        $coordA = new Coordinate([ $origin->getLatitude(), $origin->getLongitude() ]);
        $coordB = new Coordinate([ $destination->getLatitude(), $destination->getLongitude() ]);

        $distance = $geotools
            ->distance()
            ->setFrom($coordA)
            ->setTo($coordB);

        return (int) $distance->flat();
    }

    /**
     * {@inheritdoc}
     */
    public function getDuration(GeoCoordinates $origin, GeoCoordinates $destination)
    {
        $distance = $this->getDistance($origin, $destination);

        $metersPerHour = self::KILOMETERS_PER_HOUR * 1000;

        $minutes = ($distance * 60) / $metersPerHour;

        return intval($minutes * 60);
    }
}
