<?php

namespace AppBundle\Action;

use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Order;
use AppBundle\Entity\Address;
use Doctrine\Common\Persistence\ManagerRegistry;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

class CanDeliver
{
    use ActionTrait;

    /**
     * @Route(
     *     name="restaurant_can_deliver",
     *     path="/restaurants/{id}/can-deliver/{latitude},{longitude}"
     * )
     * @Method("GET")
     */
    public function canDeliverAction($id, $latitude, $longitude, Request $request)
    {
        $restaurant = $this->doctrine->getRepository('AppBundle:Restaurant')->find($id);

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
