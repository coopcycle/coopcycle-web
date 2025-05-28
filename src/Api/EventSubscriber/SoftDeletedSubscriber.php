<?php

namespace AppBundle\Api\EventSubscriber;

use Doctrine\Persistence\ManagerRegistry;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Symfony\EventListener\EventPriorities;
use Gedmo\SoftDeleteable\SoftDeleteable as SoftDeleteableInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class SoftDeletedSubscriber implements EventSubscriberInterface
{
    private $doctrine;

    private $routes = [
        'admin_restaurants_search',
        'admin_stores_search',
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

        if ($this->isAllowedRoute($request) || $this->isSoftdeleteableOperation($request)) {
            $this->doctrine->getManager()->getFilters()->enable('soft_deleteable');
        }
    }

    private function isAllowedRoute(Request $request): bool
    {
        return in_array($request->attributes->get('_route'), $this->routes);
    }

    private function isSoftdeleteableOperation(Request $request): bool
    {
        $hasApiAttributes = $request->attributes->has('_api_resource_class') && $request->attributes->has('_api_operation');

        if (!$hasApiAttributes) {
            return false;
        }

        $operation = $request->attributes->get('_api_operation');

        if (!($operation instanceof Get) && !($operation instanceof GetCollection)) {
            return false;
        }

        $resourceClass = $request->attributes->get('_api_resource_class');

        if (!in_array(SoftDeleteableInterface::class, class_implements($resourceClass))) {
            return false;
        }

        return true;
    }
}
