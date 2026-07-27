<?php

namespace AppBundle\Action;

use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Service\RoutingInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;

class Routing
{
    /**
     * @var RoutingInterface
     */
    private $routing;

    public function __construct(RoutingInterface $routing)
    {
        $this->routing = $routing;
    }

    private function decodeCoordinates($coordinates)
    {
        $coords = explode(';', $coordinates);

        return array_map(function($coord) {
            [ $latitude, $longitude ] = explode(',', $coord);
            return new GeoCoordinates($latitude, $longitude);
        }, $coords);
    }

    #[Route(path: '/routing/route/{coordinates}', name: 'routing_route', methods: ['GET'])]
    public function routeAction($coordinates): JsonResponse
    {
        $coords = $this->decodeCoordinates($coordinates);
        $data = $this->routing->route(...$coords);

        return new JsonResponse($data);
    }

    #[Route(path: '/routing/trip/{coordinates}', name: 'routing_trip', methods: ['GET'])]
    public function tripAction($coordinates): JsonResponse
    {
        $data = $this->routing->getTrip(...$this->decodeCoordinates($coordinates));

        return new JsonResponse($data);
    }
}
