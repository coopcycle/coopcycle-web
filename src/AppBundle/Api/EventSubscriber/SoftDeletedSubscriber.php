<?php

namespace AppBundle\Api\EventSubscriber;

use Doctrine\Persistence\ManagerRegistry;
use ApiPlatform\Core\EventListener\EventPriorities;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class SoftDeletedSubscriber implements EventSubscriberInterface
{
    private $doctrine;

    private $routes = [
        'api_restaurants_products_get_subresource'
    ];

    public function __construct(ManagerRegistry $doctrine)
    {
        $this->doctrine = $doctrine;
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => ['enableSoftDeleted', EventPriorities::POST_DESERIALIZE],
        ];
    }

    public function enableSoftDeleted(RequestEvent $event)
    {
        $request = $event->getRequest();

        if (!in_array($request->attributes->get('_route'), $this->routes)) {

            return;
        }

        $this->doctrine->getManager()->getFilters()->enable('soft_deleteable');
    }
}
