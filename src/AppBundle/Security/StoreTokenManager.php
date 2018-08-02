<?php

namespace AppBundle\Security;

use AppBundle\Entity\Store;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class StoreTokenManager
{
    private $tokenStorage;
    private $jwtManager;
    private $dispatcher;

    public function __construct(
        TokenStorageInterface $tokenStorage,
        JWTTokenManagerInterface $jwtManager,
        EventDispatcherInterface $dispatcher)
    {
        $this->tokenStorage = $tokenStorage;
        $this->jwtManager = $jwtManager;
        $this->dispatcher = $dispatcher;
    }

    public function create(Store $store, UserInterface $user = null)
    {
        $onJWTCreated = function (JWTCreatedEvent $event) use ($store) {
            $payload = $event->getData();
            $payload['store'] = $store->getId();
            $event->setData($payload);
        };

        $this->dispatcher->addListener(Events::JWT_CREATED, $onJWTCreated);
        $jwt = $this->jwtManager->create($user ? $user : $this->getUser($store));
        $this->dispatcher->removeListener(Events::JWT_CREATED, $onJWTCreated);

        return $jwt;
    }

    private function getUser(Store $store)
    {
        $owners = $store->getOwners();

        if ($token = $this->tokenStorage->getToken()) {
            if ($user = $token->getUser()) {
                if ($owners->contains($user)) {
                    return $user;
                }
            }

            // FIXME We should be able to choose the user
            return $owners->first();
        }

        throw new \RuntimeException('No user found to generate JWT');
    }
}
