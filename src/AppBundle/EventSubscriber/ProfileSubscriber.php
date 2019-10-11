<?php

namespace AppBundle\EventSubscriber;

use Doctrine\Common\Collections\Collection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class ProfileSubscriber implements EventSubscriberInterface
{
    private $tokenStorage;

    public function __construct(TokenStorageInterface $tokenStorage)
    {
        $this->tokenStorage = $tokenStorage;
    }

    private function findResourceInSession(Request $request, Collection $items, $sessionKey)
    {
        if (!$request->getSession()->has($sessionKey)) {
            $item = $items->first();
            $request->getSession()->set($sessionKey, $item->getId());
        }

        foreach ($items as $item) {
            if ($item->getId() === $request->getSession()->get($sessionKey)) {

                return $item;
            }
        }
    }

    public function onKernelRequest(RequestEvent $event)
    {
        $request = $event->getRequest();

        // Skip if this is an API request
        if ($request->attributes->has('_api_resource_class')) {
            return;
        }

        if (!$request->hasPreviousSession()) {

            return;
        }

        if (null === $token = $this->tokenStorage->getToken()) {

            return;
        }

        if (!is_object($user = $token->getUser())) {

            return; // e.g. anonymous authentication
        }

        if (!$user->hasRole('ROLE_STORE') && !$user->hasRole('ROLE_RESTAURANT')) {

            return;
        }

        $stores = $user->getStores();
        $restaurants = $user->getRestaurants();

        if (0 === count($stores) && 0 === count($restaurants)) {

            return;
        }

        if (count($stores) > 0 && $user->hasRole('ROLE_STORE')) {
            $request->attributes->set('_store', $this->findResourceInSession($request, $stores, '_store'));
        }


        if (count($restaurants) > 0 && $user->hasRole('ROLE_RESTAURANT')) {
            $request->attributes->set('_restaurant', $this->findResourceInSession($request, $restaurants, '_restaurant'));
        }
    }

    public static function getSubscribedEvents()
    {
        return array(
            KernelEvents::REQUEST => 'onKernelRequest',
        );
    }
}
