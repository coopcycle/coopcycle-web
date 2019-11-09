<?php

namespace AppBundle\Api\EventSubscriber;

use AppBundle\Entity\Restaurant;
use ApiPlatform\Core\DataProvider\ItemDataProviderInterface;
use ApiPlatform\Core\EventListener\EventPriorities;
use ApiPlatform\Core\Security\EventListener\DenyAccessListener;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @see https://github.com/api-platform/api-platform/issues/529
 * @see https://github.com/api-platform/api-platform/issues/709
 * @see https://github.com/api-platform/api-platform/issues/748
 */
final class SubresourceDenyAccessListener implements EventSubscriberInterface
{
    private $itemDataProvider;
    private $denyAccessListener;

    public function __construct(
        ItemDataProviderInterface $itemDataProvider,
        DenyAccessListener $denyAccessListener)
    {
        $this->itemDataProvider = $itemDataProvider;
        $this->denyAccessListener = $denyAccessListener;
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => ['security', EventPriorities::PRE_READ],
        ];
    }

    private function supportsRequest(Request $request)
    {
        return in_array($request->attributes->get('_route'), [
            'api_restaurants_orders_get_subresource',
            'api_stores_deliveries_get_subresource',
        ]);
    }

    public function security(RequestEvent $event)
    {
        $request = $event->getRequest();

        if (!$this->supportsRequest($request)) {
            return;
        }

        $subresourceContext = $request->attributes->get('_api_subresource_context');

        $parent = null;
        foreach ($subresourceContext['identifiers'] as $key => [$id, $resourceClass]) {
            if (null !== $parent = $this->itemDataProvider->getItem($resourceClass, $request->attributes->get($id), 'get')) {
                break;
            }
        }

        if (null !== $parent) {

            $operationName = sprintf('%s_get_subresource', $subresourceContext['property']);

            // Trick DenyAccessListener to make subresourceOperations work on parent resource
            $newRequest = $request->duplicate();
            $newRequest->attributes->set('data', $parent);
            $newRequest->attributes->set('_api_resource_class', get_class($parent));
            $newRequest->attributes->set('_api_subresource_operation_name', $operationName);

            $newEvent = new RequestEvent($event->getKernel(), $newRequest, $event->getRequestType());

            $this->denyAccessListener->onSecurity($newEvent);
        }
    }
}
