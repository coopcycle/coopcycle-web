<?php

namespace AppBundle\Api\EventSubscriber;

use AppBundle\Api\Exception\BadRequestHttpException;
use AppBundle\Api\Resource\Pricing as PricingResource;
use AppBundle\Entity\ApiApp;
use AppBundle\Entity\Base\GeoCoordinates;
use AppBundle\Entity\Address;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Store;
use AppBundle\Service\DeliveryManager;
use AppBundle\Service\Geocoder;
use AppBundle\Service\RoutingInterface;
use ApiPlatform\Core\EventListener\EventPriorities;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Trikoder\Bundle\OAuth2Bundle\Manager\AccessTokenManagerInterface;
use Trikoder\Bundle\OAuth2Bundle\Security\Authentication\Token\OAuth2Token;

final class PricingSubscriber implements EventSubscriberInterface
{
    private $deliveryManager;
    private $routing;
    private $geocoder;
    private $tokenStorage;
    private $doctrine;
    private $accessTokenManager;

    public function __construct(
        DeliveryManager $deliveryManager,
        RoutingInterface $routing,
        Geocoder $geocoder,
        TokenStorageInterface $tokenStorage,
        ManagerRegistry $doctrine,
        AccessTokenManagerInterface $accessTokenManager
    ) {
        $this->deliveryManager = $deliveryManager;
        $this->routing = $routing;
        $this->geocoder = $geocoder;
        $this->tokenStorage = $tokenStorage;
        $this->doctrine = $doctrine;
        $this->accessTokenManager = $accessTokenManager;
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::VIEW => ['calculatePrice', EventPriorities::POST_VALIDATE],
        ];
    }

    public function calculatePrice(ViewEvent $event)
    {
        $request = $event->getRequest();

        $route = $request->attributes->get('_route');

        $routes = [
            'api_calculate_price_requests_get_collection',
            'api_pricings_calc_price_collection'
        ];

        if (!in_array($route, $routes)) {
            return;
        }

        if ('api_calculate_price_requests_get_collection' === $route) {
            return $this->handleCalculatePriceLegacy($event);
        }

        if ('api_pricings_calc_price_collection' === $route) {
            return $this->handleCalculatePrice($event);
        }
    }

    private function handleCalculatePriceLegacy(ViewEvent $event)
    {
        $request = $event->getRequest();

        if (null === $token = $this->tokenStorage->getToken()) {
            throw new AccessDeniedException();
        }

        $store = null;
        if ($token instanceof OAuth2Token) {

            $accessToken = $this->accessTokenManager->find($token->getCredentials());
            $client = $accessToken->getClient();

            $apiApp = $this->doctrine->getRepository(ApiApp::class)
                ->findOneByOauth2Client($client);

            $store = $apiApp->getStore();
        }

        if (!$store) {
            throw new BadRequestHttpException('No store found in context');
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

    private function handleCalculatePrice(ViewEvent $event)
    {
        $result = $event->getControllerResult();

        if (!$result instanceof PricingResource) {
            return;
        }

        $event->setResponse(new JsonResponse($result->price));
    }
}
