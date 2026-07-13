<?php

namespace AppBundle\Service\Routing;

use AppBundle\Entity\Base\GeoCoordinates;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;

class ValhallaWithFallback extends Base
{
    /**
     * @var Valhalla
     */
    private $valhalla;
    /**
     * @var Fallback
     */
    private $fallback;

    public function __construct(Valhalla $valhalla, Fallback $fallback)
    {
        $this->valhalla = $valhalla;
        $this->fallback = $fallback;
    }

    /**
     * {@inheritdoc}
     */
    public function getPolyline(GeoCoordinates ...$coordinates)
    {
        try {
            return $this->valhalla->getPolyline(...$coordinates);
        } catch (HttpExceptionInterface $e) {
            return $this->fallback->getPolyline(...$coordinates);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getDistance(GeoCoordinates ...$coordinates)
    {
        try {
            return $this->valhalla->getDistance(...$coordinates);
        } catch (HttpExceptionInterface $e) {
            return $this->fallback->getDistance(...$coordinates);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getDuration(GeoCoordinates ...$coordinates)
    {
        try {
            return $this->valhalla->getDuration(...$coordinates);
        } catch (HttpExceptionInterface $e) {
            return $this->fallback->getDuration(...$coordinates);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getDistances(GeoCoordinates $source, GeoCoordinates ...$destinations)
    {
        try {
            return $this->valhalla->getDistances($source, ...$destinations);
        } catch (HttpExceptionInterface $e) {
            return $this->fallback->getDistances($source, ...$destinations);
        }
    }

    public function route(GeoCoordinates ...$coordinates)
    {
        try {
            return $this->valhalla->route(...$coordinates);
        } catch (HttpExceptionInterface $e) {
            return $this->fallback->route(...$coordinates);
        }
    }

    public function getTrip(GeoCoordinates ...$coordinates)
    {
        try {
            return $this->valhalla->getTrip(...$coordinates);
        } catch (HttpExceptionInterface $e) {
            return $this->fallback->route(...$coordinates);
        }
    }
}
