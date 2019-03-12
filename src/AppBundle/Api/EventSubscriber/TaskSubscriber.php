<?php

namespace AppBundle\Api\EventSubscriber;

use AppBundle\Entity\ApiApp;
use AppBundle\Entity\Store;
use ApiPlatform\Core\EventListener\EventPriorities;
use Doctrine\Common\Persistence\ManagerRegistry as DoctrineRegistry;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseForControllerResultEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Trikoder\Bundle\OAuth2Bundle\Manager\AccessTokenManagerInterface;
use Trikoder\Bundle\OAuth2Bundle\Security\Authentication\Token\OAuth2Token;

final class TaskSubscriber implements EventSubscriberInterface
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
            KernelEvents::VIEW => ['addToStore', EventPriorities::POST_WRITE],
        ];
    }

    public function addToStore(GetResponseForControllerResultEvent $event)
    {
        $request = $event->getRequest();

        if ('api_tasks_post_collection' !== $request->attributes->get('_route')) {
            return;
        }

        if (null !== ($token = $this->tokenStorage->getToken())) {

            if ($token instanceof OAuth2Token) {

                $accessToken = $this->accessTokenManager->find($token->getCredentials());
                $client = $accessToken->getClient();

                $apiApp = $this->doctrine->getRepository(ApiApp::class)
                    ->findOneByOauth2Client($client);

                // TODO
            }
        }
    }
}
