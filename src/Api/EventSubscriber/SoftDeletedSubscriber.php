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
        'api_restaurants_products_get_subresource',
        'api_restaurants_get_collection',
        'api_recurrence_rules_get_collection',
        'api_recurrence_rules_get_item',
        'api_organizations_get_collection',
        'admin_restaurants_search',
        'admin_stores_search',
        'api_stores_get_collection',
        'api_warehouses_get_collection',
        'api_vehicles_get_collection',
        'api_trailers_get_collection'
    ];

    public function __construct(ManagerRegistry $doctrine)
    {
        $this->doctrine = $doctrine;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => ['enableSoftDeleted', EventPriorities::PRE_READ],
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
