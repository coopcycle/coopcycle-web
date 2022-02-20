<?php

namespace AppBundle\Service\Routing;

use AppBundle\Service\RoutingInterface;
use AppBundle\Entity\Base\GeoCoordinates;
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
    public function getPolyline(GeoCoordinates ...$coordinates)
    {
        $points = array_map(function (GeoCoordinates $c) {
            return [ $c->getLatitude(), $c->getLongitude() ];
        }, $coordinates);

        return Polyline::encode($points);
    }

    /**
     * {@inheritdoc}
     */
    public function getDistance(GeoCoordinates ...$coordinates)
    {
        $geotools = new Geotools();

        if (count($coordinates) <= 1) {
            return 0;
        }

        $from = array_shift($coordinates);

        $totalDistance = 0;

        while (count($coordinates) > 0) {

            $to = array_shift($coordinates);

            $distance = $geotools
                ->distance()
                ->setFrom(new Coordinate([ $from->getLatitude(), $from->getLongitude() ]))
                ->setTo(new Coordinate([ $to->getLatitude(), $to->getLongitude() ]));

            $totalDistance += (int) $distance->flat();

            $from = $to;
        }

        return $totalDistance;
    }

    /**
     * {@inheritdoc}
     */
    public function getDuration(GeoCoordinates ...$coordinates)
    {
        $distance = $this->getDistance(...$coordinates);

        $metersPerHour = self::KILOMETERS_PER_HOUR * 1000;

        $minutes = ($distance * 60) / $metersPerHour;

        return intval($minutes * 60);
    }

    public function getDistances(GeoCoordinates $source, GeoCoordinates ...$destinations)
    {
        $distances = [];

        foreach ($destinations as $destination) {
            $distances[] = $this->getDistance($source, $destination);
        }

        return $distances;
    }

    public function route(GeoCoordinates ...$coordinates)
    {
        return [
            'code' => 'Ok',
            'routes' => [
                [
                    'geometry' => $this->getPolyline(...$coordinates),
                    'distance' => $this->getDistance(...$coordinates),
                    'duration' => $this->getDuration(...$coordinates)
                ]

            ]
        ];
    }
}
