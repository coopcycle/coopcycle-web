<?php

namespace AppBundle\Action;

use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Restaurant;
use AppBundle\Service\RoutingInterface;
use Doctrine\Common\Persistence\ManagerRegistry as DoctrineRegistry;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class CanDeliver
{
    private $doctrine;
    private $routing;

    public function __construct(DoctrineRegistry $doctrine, RoutingInterface $routing)
    {
        $this->doctrine = $doctrine;
        $this->routing = $routing;
    }

    /**
     * @Route(
     *     name="restaurant_can_deliver",
     *     path="/restaurants/{id}/can-deliver/{latitude},{longitude}"
     * )
     * @Method("GET")
     */
    public function canDeliverAction($id, $latitude, $longitude, Request $request)
    {
        $restaurant = $this->doctrine->getRepository(Restaurant::class)->find($id);

        // TODO Manage 404

        $origin = $restaurant->getAddress()->getGeo();
        $destination = new GeoCoordinates($latitude, $longitude);

        $distance = $this->routing->getDistance($origin, $destination);

        if ($distance > $restaurant->getMaxDistance()) {
            return new JsonResponse('no', 400);
        }

        return new JsonResponse('yes', 200);
    }
}
