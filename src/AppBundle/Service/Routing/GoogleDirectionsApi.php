<?php

namespace AppBundle\Service\Routing;

use AppBundle\Service\RoutingInterface;
use AppBundle\Entity\GeoCoordinates;
use Polyline;

class GoogleDirectionsApi implements RoutingInterface
{
    private $apiKey;

    public function __construct($apiKey)
    {
        $this->apiKey = $apiKey;
    }

    public function getPolyline(GeoCoordinates $origin, GeoCoordinates $destination)
    {
        $url = 'https://maps.googleapis.com/maps/api/directions/json?mode=bicycling'
            . '&origin=' . $origin->getLatitude() . ',' . $origin->getLongitude()
            . '&destination=' . $destination->getLatitude() . ',' . $destination->getLongitude()
            . '&key=' . $this->apiKey;

        $response = file_get_contents($url);

        if ($response) {
            $data = json_decode($response, true);
            if ($data['status'] === 'OK') {
                $polyline = $data['routes'][0]['overview_polyline']['points'];

                return $polyline;
            }
        }

        throw new \Exception('Could not get route');
    }

    public function getPoints(GeoCoordinates $origin, GeoCoordinates $destination)
    {
        $polyline = $this->getPolyline($origin, $destination);
        $points = Polyline::decode($polyline);

        return Polyline::pair($points);
    }
}