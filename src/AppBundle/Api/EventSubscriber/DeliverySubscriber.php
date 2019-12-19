<?php

namespace AppBundle\Api\EventSubscriber;

use AppBundle\Entity\Store;
use AppBundle\Security\TokenStoreExtractor;
use AppBundle\Service\RoutingInterface;
use Doctrine\Persistence\ManagerRegistry;
use ApiPlatform\Core\EventListener\EventPriorities;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Authentication\Token\JWTUserToken;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class DeliverySubscriber implements EventSubscriberInterface
{
    private $doctrine;
    private $tokenStorage;
    private $storeExtractor;
    private $routing;

    private static $matchingRoutes = [
        'api_deliveries_get_item',
        'api_deliveries_post_collection',
        'api_deliveries_check_collection'
    ];

    public function __construct(
        ManagerRegistry $doctrine,
        TokenStorageInterface $tokenStorage,
        TokenStoreExtractor $storeExtractor,
        RoutingInterface $routing)
    {
        $this->doctrine = $doctrine;
        $this->tokenStorage = $tokenStorage;
        $this->storeExtractor = $storeExtractor;
        $this->routing = $routing;
    }

    public static function getSubscribedEvents()
    {
        // @see https://api-platform.com/docs/core/events/#built-in-event-listeners
        return [
            KernelEvents::REQUEST => [
                ['accessControl', EventPriorities::POST_DESERIALIZE],
            ],
            KernelEvents::VIEW => [
                ['setDefaults', EventPriorities::PRE_VALIDATE],
                ['calculate', EventPriorities::PRE_VALIDATE],
                ['handleCheckResponse', EventPriorities::POST_VALIDATE],
                ['addToStore', EventPriorities::POST_WRITE],
            ],
        ];
    }

    private function matchRoute(Request $request)
    {
        return in_array($request->attributes->get('_route'), self::$matchingRoutes);
    }

    public function accessControl(RequestEvent $event)
    {
        $request = $event->getRequest();
        if (!$this->matchRoute($request)) {
            return;
        }

        $delivery = $request->attributes->get('data');

        if (null !== ($token = $this->tokenStorage->getToken())) {

            if ($token instanceof JWTUserToken) {

                $user = $token->getUser();
                $store = $delivery->getStore();

                if ($store && is_object($user) && is_callable([ $user, 'ownsStore' ]) && $user->ownsStore($store)) {
                    return;
                }

            } else {
                // TODO Move this to Delivery entity access_control
                $roles = $token->getRoles();
                foreach ($roles as $role) {
                    if ($role->getRole() === 'ROLE_OAUTH2_DELIVERIES') {

                        $store = $this->storeExtractor->extractStore();

                        if (null === $delivery->getStore()) {
                            return;
                        }

                        if ($delivery->getStore() === $store) {
                            return;
                        }
                    }
                }
            }
        }

        throw new AccessDeniedException();
    }

    public function setDefaults(ViewEvent $event)
    {
        $request = $event->getRequest();
        if (!$this->matchRoute($request)) {
            return;
        }

        $delivery = $event->getControllerResult();

        $pickup = $delivery->getPickup();
        $dropoff = $delivery->getDropoff();

        if (null === $store = $delivery->getStore()) {
            $store = $this->storeExtractor->extractStore();
        }

        // If no pickup address is specified, use the store address
        if (null === $pickup->getAddress() && null !== $store) {
            $pickup->setAddress($store->getAddress());
        }

        // If no pickup time is specified, calculate it
        if (null !== $dropoff->getDoneBefore() && null === $pickup->getDoneBefore()) {
            if (null !== $dropoff->getAddress() && null !== $pickup->getAddress()) {

                $duration = $this->routing->getDuration(
                    $pickup->getAddress()->getGeo(),
                    $dropoff->getAddress()->getGeo()
                );

                $pickupDoneBefore = clone $dropoff->getDoneBefore();
                $pickupDoneBefore->modify(sprintf('-%d seconds', $duration));

                $pickup->setDoneBefore($pickupDoneBefore);
            }
        }
    }

    public function addToStore(ViewEvent $event)
    {
        $request = $event->getRequest();

        if ('api_deliveries_post_collection' !== $request->attributes->get('_route')) {
            return;
        }

        $store = $this->storeExtractor->extractStore();

        if (null === $store) {
            return;
        }

        $delivery = $event->getControllerResult();

        $store->addDelivery($delivery);
        $this->doctrine->getManagerForClass(Store::class)->flush();
    }

    public function calculate(ViewEvent $event)
    {
        $request = $event->getRequest();

        if (!$this->matchRoute($request)) {
            return;
        }

        $delivery = $event->getControllerResult();

        $osrmData = $this->routing->getRawResponse(
            $delivery->getPickup()->getAddress()->getGeo(),
            $delivery->getDropoff()->getAddress()->getGeo()
        );

        $distance = $osrmData['routes'][0]['distance'];

        $delivery->setDistance(ceil($distance));
    }

    // FIXME
    // This is here to avoid the following issue when using write=false in the operation
    // Unable to generate an IRI for the item of type "AppBundle\Entity\Delivery"
    // We just respond HTTP 200, with an empty response
    // This may be remove once https://github.com/api-platform/core/pull/3150 is merged
    public function handleCheckResponse(ViewEvent $event)
    {
        $request = $event->getRequest();
        if ('api_deliveries_check_collection' !== $request->attributes->get('_route')) {
            return;
        }

        $event->setResponse(new JsonResponse([], 200));
    }
}
