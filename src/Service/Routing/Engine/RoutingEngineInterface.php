<?php

namespace AppBundle\Service\Routing\Engine;

use AppBundle\Entity\Base\GeoCoordinates;

interface RoutingEngineInterface
{
    /**
     * @param GeoCoordinates[] ...$coordinates
     */
    public function getPolyline(GeoCoordinates ...$coordinates);

    /**
     * @param GeoCoordinates[] ...$coordinates
     */
    public function getDistance(GeoCoordinates ...$coordinates);

    /**
     * @param GeoCoordinates[] ...$coordinates
     */
    public function getDuration(GeoCoordinates ...$coordinates);

    /**
     * @param GeoCoordinates[] ...$coordinates
     */
    public function getDistances(GeoCoordinates $source, GeoCoordinates ...$coordinates);

    /**
     * @param GeoCoordinates[] ...$coordinates
     * @return array OSRM-shaped response
     */
    public function route(GeoCoordinates ...$coordinates);

    /**
     * @param GeoCoordinates[] ...$coordinates
     * @return array OSRM-shaped response (optimized trip)
     */
    public function trip(GeoCoordinates ...$coordinates);
}
