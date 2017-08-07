<?php

namespace AppBundle\Action;

use AppBundle\Entity\Order;
use Doctrine\Common\Persistence\ManagerRegistry;
use GuzzleHttp\Client;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class Routing
{
    /**
     * @var Client
     */
    private $client;

    /**
     * @param Client $client
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
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
        // /route/v1/{profile}/{coordinates}?alternatives={true|false}&steps={true|false}&geometries={polyline|polyline6|geojson}&overview={full|simplified|false}&annotations={true|false}
        // http://router.project-osrm.org/route/v1/driving/13.388860,52.517037;13.397634,52.529407;13.428555,52.523219?overview=false

        $origin = $request->query->get('origin');
        $destination = $request->query->get('destination');

        list($originLat, $originLng) = explode(',', $origin);
        list($destinationLat, $destinationLng) = explode(',', $destination);

        $data = $this->client->request('GET', "/route/v1/bicycle/{$originLng},{$originLat};{$destinationLng},{$destinationLat}?overview=full");

        return new JsonResponse($data, 200, [], true);
    }
}