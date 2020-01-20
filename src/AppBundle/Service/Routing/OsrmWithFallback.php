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
    public function getPolyline(GeoCoordinates $origin, GeoCoordinates $destination)
    {
        try {
            return $this->osrm->getPolyline($origin, $destination);
        } catch (GuzzleException $e) {
            return $this->fallback->getPolyline($origin, $destination);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getDistance(GeoCoordinates $origin, GeoCoordinates $destination)
    {
        try {
            return $this->osrm->getDistance($origin, $destination);
        } catch (GuzzleException $e) {
            return $this->fallback->getDistance($origin, $destination);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getDuration(GeoCoordinates $origin, GeoCoordinates $destination)
    {
        try {
            return $this->osrm->getDuration($origin, $destination);
        } catch (GuzzleException $e) {
            return $this->fallback->getDuration($origin, $destination);
        }
    }
}
