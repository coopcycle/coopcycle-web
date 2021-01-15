<?php

namespace AppBundle\Action;

use AppBundle\Entity\Address;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Service\RoutingInterface;
use Doctrine\Persistence\ManagerRegistry;
use AppBundle\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class CanDeliver
{
    private $doctrine;
    private $routing;

    public function __construct(
        ManagerRegistry $doctrine,
        RoutingInterface $routing,
        ExpressionLanguage $expressionLanguage)
    {
        $this->doctrine = $doctrine;
        $this->routing = $routing;
        $this->expressionLanguage = $expressionLanguage;
    }

    /**
     * @Route(
     *     name="restaurant_can_deliver",
     *     path="/restaurants/{id}/can-deliver/{latitude},{longitude}",
     *     methods={"GET"}
     * )
     */
    public function canDeliverAction($id, $latitude, $longitude, Request $request)
    {
        $restaurant = $this->doctrine->getRepository(LocalBusiness::class)->find($id);

        // TODO Manage 404

        $origin = $restaurant->getAddress()->getGeo();
        $destination = new GeoCoordinates($latitude, $longitude);
        $destinationAddress = new Address();
        $destinationAddress->setGeo($destination);

        $distance = $this->routing->getDistance($origin, $destination);

        if (!$restaurant->canDeliverAddress($destinationAddress, $distance, $this->expressionLanguage)) {
            return new JsonResponse('no', 400);
        }

        return new JsonResponse('yes', 200);
    }
}
