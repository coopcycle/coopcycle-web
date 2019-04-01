<?php

namespace AppBundle\Api\EventSubscriber;

use AppBundle\Entity\ApiApp;
use AppBundle\Entity\Store;
use AppBundle\Service\RoutingInterface;
use Doctrine\Common\Persistence\ManagerRegistry as DoctrineRegistry;
use ApiPlatform\Core\EventListener\EventPriorities;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Authentication\Token\JWTUserToken;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForControllerResultEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Trikoder\Bundle\OAuth2Bundle\Security\Authentication\Token\OAuth2Token;
use Trikoder\Bundle\OAuth2Bundle\Manager\AccessTokenManagerInterface;

final class DeliverySubscriber implements EventSubscriberInterface
{
    private $doctrine;
    private $tokenStorage;
    private $accessTokenManager;
    private $routing;

    public function __construct(
        DoctrineRegistry $doctrine,
        TokenStorageInterface $tokenStorage,
        AccessTokenManagerInterface $accessTokenManager,
        RoutingInterface $routing)
    {
        $this->doctrine = $doctrine;
        $this->tokenStorage = $tokenStorage;
        $this->accessTokenManager = $accessTokenManager;
        $this->routing = $routing;
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => ['accessControl', EventPriorities::PRE_READ],
            KernelEvents::VIEW => [
                ['setDefaults', EventPriorities::PRE_VALIDATE],
                ['addToStore', EventPriorities::POST_WRITE],
            ],
        ];
    }

    private function getStore($token)
    {
        if ($token instanceof OAuth2Token) {

            $accessToken = $this->accessTokenManager->find($token->getCredentials());
            $client = $accessToken->getClient();

            $apiApp = $this->doctrine->getRepository(ApiApp::class)
                ->findOneByOauth2Client($client);

            return $apiApp->getStore();
        } else if ($token->hasAttribute('store')) {

            return $token->getAttribute('store');
        }
    }

    public function accessControl(GetResponseEvent $event)
    {
        $request = $event->getRequest();

        if ('api_deliveries_post_collection' !== $request->attributes->get('_route')) {
            return;
        }

        if (null !== ($token = $this->tokenStorage->getToken())) {

            if ($token instanceof JWTUserToken && $token->hasAttribute('store')) {
                $user = $token->getUser();
                $store = $token->getAttribute('store');

                if ($user->getStores()->contains($store)) {

                    return;
                }
            } else {
                // TODO Move this to Delivery entity access_control
                $roles = $token->getRoles();
                foreach ($roles as $role) {
                    if ($role->getRole() === 'ROLE_OAUTH2_DELIVERIES') {
                        return;
                    }
                }
            }
        }

        throw new AccessDeniedException();
    }

    public function setDefaults(GetResponseForControllerResultEvent $event)
    {
        $request = $event->getRequest();

        if ('api_deliveries_post_collection' !== $request->attributes->get('_route')) {
            return;
        }

        if (null !== ($token = $this->tokenStorage->getToken())) {

            $delivery = $event->getControllerResult();
            $store = $this->getStore($token);

            if (null !== $store) {

                $pickup = $delivery->getPickup();
                $dropoff = $delivery->getDropoff();

                // If no pickup address is specified, use the store address
                if (null === $pickup->getAddress()) {
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
        }
    }

    public function addToStore(GetResponseForControllerResultEvent $event)
    {
        $request = $event->getRequest();

        if ('api_deliveries_post_collection' !== $request->attributes->get('_route')) {
            return;
        }

        if (null !== ($token = $this->tokenStorage->getToken())) {

            $delivery = $event->getControllerResult();
            $store = $this->getStore($token);

            if (null !== $store) {
                $store->addDelivery($delivery);
                $this->doctrine->getManagerForClass(Store::class)->flush();
            }
        }
    }
}
