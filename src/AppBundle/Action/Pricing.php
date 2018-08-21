<?php

namespace AppBundle\Action;

use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Address;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Store;
use AppBundle\Service\DeliveryManager;
use AppBundle\Service\Geocoder;
use AppBundle\Service\RoutingInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class Pricing
{
    private $deliveryManager;
    private $routing;
    private $geocoder;
    private $tokenStorage;

    public function __construct(
        DeliveryManager $deliveryManager,
        RoutingInterface $routing,
        Geocoder $geocoder,
        TokenStorageInterface $tokenStorage)
    {
        $this->deliveryManager = $deliveryManager;
        $this->routing = $routing;
        $this->geocoder = $geocoder;
        $this->tokenStorage = $tokenStorage;
    }

    /**
     * @Route(
     *   name="api_calculate_price",
     *   path="/pricing/calculate-price"
     * )
     * @Method("GET")
     */
    public function calculatePriceAction(Request $request)
    {
        if (null === $token = $this->tokenStorage->getToken()) {
            // TODO Throw Exception
            return;
        }

        if (null === $store = $token->getAttribute('store')) {
            // TODO Throw Exception
            return;
        }

        if (!$request->query->has('dropoffAddress')) {
            throw new BadRequestHttpException('Parameter "dropoffAddress" is mandatory');
        }

        $pickupAddress = $store->getAddress();
        $dropoffAddress = $this->geocoder->geocode($request->query->get('dropoffAddress'));

        // TODO Check address has been geocoded

        $data = $this->routing->getRawResponse(
            $pickupAddress->getGeo(),
            $dropoffAddress->getGeo()
        );

        $distance = $data['routes'][0]['distance'];

        $delivery = new Delivery();
        $delivery->getPickup()->setAddress($pickupAddress);
        $delivery->getDropoff()->setAddress($dropoffAddress);
        $delivery->setVehicle(Delivery::VEHICLE_BIKE);
        $delivery->setWeight($request->query->get('weight', null));
        $delivery->setDistance(ceil($distance));

        $price = $this->deliveryManager->getPrice($delivery, $store->getPricingRuleSet());

        // TODO Throw HTTP 400 when price can't be calculated

        return new JsonResponse($price);
    }
}
