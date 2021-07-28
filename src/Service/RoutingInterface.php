<?php

namespace AppBundle\Service;

use AppBundle\Entity\Base\GeoCoordinates;

interface RoutingInterface
{
    /**
     * @param GeoCoordinates[] ...$coordinates
     */
    public function getPolyline(GeoCoordinates ...$coordinates);

    /**
     * @param GeoCoordinates[] ...$coordinates
     */
    public function getPoints(GeoCoordinates ...$coordinates);

    /**
     * @param GeoCoordinates[] ...$coordinates
     */
    public function getDistance(GeoCoordinates ...$coordinates);

    /**
     * @param GeoCoordinates[] ...$coordinates
     */
    public function getDuration(GeoCoordinates ...$coordinates);

    /**
     * @param GeoCoordinates $source
     * @param GeoCoordinates[] ...$coordinates
     */
    public function getDistances(GeoCoordinates $source, GeoCoordinates ...$coordinates);

    /**
     * @param GeoCoordinates[] ...$coordinates
     * @return array
     */
    public function route(GeoCoordinates ...$coordinates);
}
