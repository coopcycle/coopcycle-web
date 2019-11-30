<?php

namespace AppBundle\Action;

use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Service\RoutingInterface;
use Doctrine\Common\Persistence\ManagerRegistry;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class Routing
{
    /**
     * @var RoutingInterface
     */
    private $routing;

    /**
     * @param RoutingInterface $routing
     */
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

    /**
     * @Route(
     *     path="/routing/route/{coordinates}",
     *     name="routing_route",
     *     methods={"GET"}
     * )
     */
    public function routeAction($coordinates, Request $request): JsonResponse
    {
        $data = $this->routing->getServiceResponse(
            'route',
            $this->decodeCoordinates($coordinates),
            [
                'steps' => 'true',
                'overview' => 'full'
            ]
        );

        return new JsonResponse($data);
    }

    /**
     * @Route(
     *     path="/routing/trip/{coordinates}",
     *     name="routing_trip",
     *     methods={"GET"}
     * )
     */
    public function tripAction($coordinates, Request $request): JsonResponse
    {
        $data = $this->routing->getServiceResponse(
            'trip',
            $this->decodeCoordinates($coordinates),
            [
                'steps' => 'true',
                'overview' => 'full'
            ]
        );

        return new JsonResponse($data);
    }
}
