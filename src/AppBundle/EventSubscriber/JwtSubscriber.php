<?php

namespace AppBundle\EventSubscriber;

use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTManagerInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTDecodeFailureException;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Authentication\Token\PreAuthenticationJWTUserToken;

class JwtSubscriber implements EventSubscriberInterface
{
    private $jwtManager;
    private $tokenStorage;

    public function __construct(JWTManagerInterface $jwtManager, TokenStorageInterface $tokenStorage)
    {
        $this->jwtManager = $jwtManager;
        $this->tokenStorage = $tokenStorage;
    }

    public function onKernelRequest(GetResponseEvent $event)
    {
        $request = $event->getRequest();

        if (!$request->hasPreviousSession()) {

            return;
        }

        if (null === $token = $this->tokenStorage->getToken()) {

            return;
        }

        if (!is_object($user = $token->getUser())) {

            return; // e.g. anonymous authentication
        }

        if ($request->getSession()->has('_jwt')) {

            $jwt = $request->getSession()->get('_jwt');

            try {
                $token = new PreAuthenticationJWTUserToken($jwt);
                $this->jwtManager->decode($token);
            } catch (JWTDecodeFailureException $e) {
                if (JWTDecodeFailureException::EXPIRED_TOKEN === $e->getReason()) {
                    $request->getSession()->set('_jwt', $this->jwtManager->create($user));
                }
            }

        } else {
            $request->getSession()->set('_jwt', $this->jwtManager->create($user));
        }
    }

    public static function getSubscribedEvents()
    {
        return array(
            KernelEvents::REQUEST => 'onKernelRequest',
        );
    }
}
