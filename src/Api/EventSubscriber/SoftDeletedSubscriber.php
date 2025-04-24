<?php

namespace AppBundle\Api\EventSubscriber;

use Doctrine\Persistence\ManagerRegistry;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Core\EventListener\EventPriorities;
use Gedmo\SoftDeleteable\SoftDeleteable as SoftDeleteableInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class SoftDeletedSubscriber implements EventSubscriberInterface
{
    private $doctrine;

    private $routes = [
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

        if (!$request->attributes->has('_api_resource_class') || !$request->attributes->has('_api_operation')) {
            return;
        }

        $operation = $request->attributes->get('_api_operation');

        if (!($operation instanceof Get) && !($operation instanceof GetCollection)) {
            return;
        }

        $resourceClass = $request->attributes->get('_api_resource_class');

        if (!in_array(SoftDeleteableInterface::class, class_implements($resourceClass))) {
            return;
        }

        $this->doctrine->getManager()->getFilters()->enable('soft_deleteable');
    }
}
