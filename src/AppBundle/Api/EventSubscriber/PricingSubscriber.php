<?php

namespace AppBundle\Api\EventSubscriber;

use AppBundle\Api\Exception\BadRequestHttpException;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Address;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Store;
use AppBundle\Service\DeliveryManager;
use AppBundle\Service\Geocoder;
use AppBundle\Service\RoutingInterface;
use ApiPlatform\Core\EventListener\EventPriorities;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\GetResponseForControllerResultEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class PricingSubscriber implements EventSubscriberInterface
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

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::VIEW => ['calculatePrice', EventPriorities::POST_VALIDATE],
        ];
    }

    public function calculatePrice(GetResponseForControllerResultEvent $event)
    {
        $request = $event->getRequest();

        if ('api_calculate_price_requests_get_collection' !== $request->attributes->get('_route')) {
            return;
        }

        if (null === $token = $this->tokenStorage->getToken()) {
            // TODO Throw Exception
            return;
        }

        if (null === $store = $token->getAttribute('store')) {
            // TODO Throw Exception
            return;
        }

        if (!$request->query->has('dropoffAddress')) {
            throw new BadRequestHttpException('Parameter dropoffAddress is mandatory');
        }

        $pickupAddress = $store->getAddress();
        $dropoffAddress = $this->geocoder->geocode($request->query->get('dropoffAddress'));

        if (null === $dropoffAddress) {
            throw new BadRequestHttpException('Address could not be geocoded');
        }

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

        if (null === $price) {
            throw new BadRequestHttpException('Price could not be calculated');
        }

        $event->setResponse(new JsonResponse($price));
    }
}
