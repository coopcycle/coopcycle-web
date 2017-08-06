<?php

namespace AppBundle\Action;

use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Order;
use AppBundle\Service\RoutingInterface;
use Doctrine\Common\Persistence\ManagerRegistry;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
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
     * @param Client $client
     */
    public function __construct(RoutingInterface $routing)
    {
        $this->routing = $routing;
    }

    /**
     * @Route(
     *     path="/routing/route",
     *     name="routing_route"
     * )
     * @Method("GET")
     */
    public function routeAction(Request $request): JsonResponse
    {
        $origin = $request->query->get('origin');
        $destination = $request->query->get('destination');

        list($originLat, $originLng) = explode(',', $origin);
        list($destinationLat, $destinationLng) = explode(',', $destination);

        $data = $this->routing->getRawResponse(
            new GeoCoordinates($originLat, $originLng),
            new GeoCoordinates($destinationLat, $destinationLng)
        );

        return new JsonResponse($data);
    }
}
