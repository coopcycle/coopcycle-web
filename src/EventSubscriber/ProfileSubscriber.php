<?php

namespace AppBundle\EventSubscriber;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Store;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class ProfileSubscriber implements EventSubscriberInterface
{
    private $tokenStorage;
    private $urlGenerator;

    private static $blacklist = [
        'profile_notifications',
        'profile_jwt',
    ];

    public function __construct(TokenStorageInterface $tokenStorage, UrlGeneratorInterface $urlGenerator)
    {
        $this->tokenStorage = $tokenStorage;
        $this->urlGenerator = $urlGenerator;
    }

    private function findResourceInSession(Request $request, Collection $items, $sessionKey)
    {
        if (count($items) === 0) {
            return;
        }

        if ($request->getSession()->has($sessionKey)) {
            foreach ($items as $item) {
                if ($item->getId() === $request->getSession()->get($sessionKey)) {
                    $request->attributes->set($sessionKey, $item);
                    return;
                }
            }
            // There is something in session, but we couldn't find it
            $request->getSession()->remove($sessionKey);
        }

        $item = $items->first();
        $request->getSession()->set($sessionKey, $item->getId());
        $request->attributes->set($sessionKey, $item);
    }

    public function onKernelRequest(RequestEvent $event)
    {
        $request = $event->getRequest();

        if (!$request->attributes->has('_route')) {
            return;
        }

        $route = $request->attributes->get('_route');

        if (in_array($route, self::$blacklist)) {
            return;
        }

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

        if ($route === 'dashboard' && ($request->query->has('store') || $request->query->has('restaurant'))) {
            if ($request->query->has('store')) {
                foreach ($stores as $store) {
                    if ($store->getId() === $request->query->getInt('store')) {
                        $request->getSession()->set('_store', $store->getId());
                        $request->getSession()->remove('_restaurant');
                        $event->setResponse(
                            new RedirectResponse($this->urlGenerator->generate('dashboard'))
                        );
                        return;
                    }
                }
            }
            if ($request->query->has('restaurant')) {
                foreach ($restaurants as $restaurant) {
                    if ($restaurant->getId() === $request->query->getInt('restaurant')) {
                        $request->getSession()->set('_restaurant', $restaurant->getId());
                        $request->getSession()->remove('_store');
                        $event->setResponse(
                            new RedirectResponse($this->urlGenerator->generate('dashboard'))
                        );
                        return;
                    }
                }
            }
        }

        if ($request->getSession()->has('_store')) {
            $this->findResourceInSession($request, $stores, '_store');
        } elseif ($request->getSession()->has('_restaurant')) {
            $this->findResourceInSession($request, $restaurants, '_restaurant');
        } else {
            if (count($stores) > 0 && count($restaurants) > 0) {
                $this->findResourceInSession($request, $stores, '_store');
            } else {
                if (count($stores) > 0) {
                    $this->findResourceInSession($request, $stores, '_store');
                }
                if (count($restaurants)) {
                    $this->findResourceInSession($request, $restaurants, '_restaurant');
                }
            }
        }
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return array(
            KernelEvents::REQUEST => 'onKernelRequest',
        );
    }
}
