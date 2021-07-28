<?php

namespace AppBundle\Service\Routing;

use AppBundle\Entity\Base\GeoCoordinates;
use GuzzleHttp\Exception\GuzzleException;

class OsrmWithFallback extends Base
{
    private $osrm;
    private $fallback;

    /**
     * @param Osrm $osrm
     * @param Fallback $fallback
     */
    public function __construct(Osrm $osrm, Fallback $fallback)
    {
        $this->osrm = $osrm;
        $this->fallback = $fallback;
    }

    /**
     * {@inheritdoc}
     */
    public function getPolyline(GeoCoordinates ...$coordinates)
    {
        try {
            return $this->osrm->getPolyline(...$coordinates);
        } catch (GuzzleException $e) {
            return $this->fallback->getPolyline(...$coordinates);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getDistance(GeoCoordinates ...$coordinates)
    {
        try {
            return $this->osrm->getDistance(...$coordinates);
        } catch (GuzzleException $e) {
            return $this->fallback->getDistance(...$coordinates);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getDuration(GeoCoordinates ...$coordinates)
    {
        try {
            return $this->osrm->getDuration(...$coordinates);
        } catch (GuzzleException $e) {
            return $this->fallback->getDuration(...$coordinates);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getDistances(GeoCoordinates $source, GeoCoordinates ...$destinations)
    {
        try {
            return $this->osrm->getDistances($source, ...$destinations);
        } catch (GuzzleException $e) {
            return $this->fallback->getDistances($source, ...$destinations);
        }
    }

    public function route(GeoCoordinates ...$coordinates)
    {
        try {
            return $this->osrm->route(...$coordinates);
        } catch (GuzzleException $e) {
            return $this->fallback->route(...$coordinates);
        }
    }
}
