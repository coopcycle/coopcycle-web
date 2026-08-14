<?php

namespace AppBundle\Service\Routing;

use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Service\Routing\Engine\ValhallaRoutingEngine;

class Valhalla extends Base
{
    /**
     * @var ValhallaRoutingEngine
     */
    private $engine;

    public function __construct(ValhallaRoutingEngine $engine)
    {
        $this->engine = $engine;
    }

    /**
     * {@inheritdoc}
     */
    public function getPolyline(GeoCoordinates ...$coordinates)
    {
        return $this->engine->getPolyline(...$coordinates);
    }

    /**
     * {@inheritdoc}
     */
    public function getDistance(GeoCoordinates ...$coordinates)
    {
        return $this->engine->getDistance(...$coordinates);
    }

    /**
     * {@inheritdoc}
     */
    public function getDuration(GeoCoordinates ...$coordinates)
    {
        return $this->engine->getDuration(...$coordinates);
    }

    /**
     * {@inheritdoc}
     */
    public function getDistances(GeoCoordinates $source, GeoCoordinates ...$destinations)
    {
        return $this->engine->getDistances($source, ...$destinations);
    }

    public function route(GeoCoordinates ...$coordinates)
    {
        return $this->engine->route(...$coordinates);
    }

    /**
     * OSRM-shaped optimized trip response.
     */
    public function getTrip(GeoCoordinates ...$coordinates)
    {
        return $this->engine->trip(...$coordinates);
    }
}
