<?php

namespace AppBundle\Api\EventSubscriber;

use AppBundle\Action\Utils\TokenStorageTrait;
use AppBundle\Entity\RemotePushToken;
use ApiPlatform\Core\EventListener\EventPriorities;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Psr\Log\LoggerInterface;

final class RemotePushTokenSubscriber implements EventSubscriberInterface
{
    use TokenStorageTrait;

    protected $tokenStorage;
    protected $doctrine;
    protected $logger;

    public function __construct(TokenStorageInterface $tokenStorage, ManagerRegistry $doctrine, LoggerInterface $logger)
    {
        $this->tokenStorage = $tokenStorage;
        $this->doctrine = $doctrine;
        $this->logger = $logger;
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::VIEW => ['createRemotePushToken', EventPriorities::POST_VALIDATE],
        ];
    }

    public function createRemotePushToken(ViewEvent $event)
    {
        $request = $event->getRequest();

        if ('api_create_remote_push_token_requests_post_collection' !== $request->attributes->get('_route')) {
            return;
        }

        $this->logger->info(sprintf('Storing APN token for user %s', $this->getUser()->getUsername()));

        $createRemotePushTokenRequest = $event->getControllerResult();

        $remotePushToken = $this->doctrine->getRepository(RemotePushToken::class)
            ->findOneBy([
                'user' => $this->getUser(),
                'platform' => $createRemotePushTokenRequest->platform
            ]);

        if ($remotePushToken) {

            $remotePushToken->setToken($createRemotePushTokenRequest->token);

            $event->setResponse(new JsonResponse(null, 204));
        } else {

            $remotePushToken = new RemotePushToken();
            $remotePushToken->setUser($this->getUser());
            $remotePushToken->setPlatform($createRemotePushTokenRequest->platform);
            $remotePushToken->setToken($createRemotePushTokenRequest->token);

            $this->doctrine->getManagerForClass(RemotePushToken::class)->persist($remotePushToken);

            $event->setResponse(new JsonResponse(null, 201));
        }

        $this->doctrine->getManagerForClass(RemotePushToken::class)->flush();
    }
}
