<?php

namespace AppBundle\Api\EventSubscriber;

use AppBundle\Entity\ApiApp;
use AppBundle\Entity\Store;
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

    public function __construct(
        DoctrineRegistry $doctrine,
        TokenStorageInterface $tokenStorage,
        AccessTokenManagerInterface $accessTokenManager)
    {
        $this->doctrine = $doctrine;
        $this->tokenStorage = $tokenStorage;
        $this->accessTokenManager = $accessTokenManager;
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => ['accessControl', EventPriorities::PRE_READ],
            KernelEvents::VIEW => ['addToStore', EventPriorities::POST_WRITE],
        ];
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

    public function addToStore(GetResponseForControllerResultEvent $event)
    {
        $request = $event->getRequest();

        if ('api_deliveries_post_collection' !== $request->attributes->get('_route')) {
            return;
        }

        if (null !== ($token = $this->tokenStorage->getToken())) {

            $delivery = $event->getControllerResult();
            $store = null;

            if ($token instanceof OAuth2Token) {

                $accessToken = $this->accessTokenManager->find($token->getCredentials());
                $client = $accessToken->getClient();

                $apiApp = $this->doctrine->getRepository(ApiApp::class)
                    ->findOneByOauth2Client($client);

                $store = $apiApp->getStore();
            } else if ($token->hasAttribute('store')) {
                $store = $token->getAttribute('store');
            }

            if (null !== $store) {
                $store->addDelivery($delivery);
                $this->doctrine->getManagerForClass(Store::class)->flush();
            }
        }
    }
}
