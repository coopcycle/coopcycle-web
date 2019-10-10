<?php

namespace AppBundle\Api\EventSubscriber;

use AppBundle\Entity\Restaurant;
use ApiPlatform\Core\DataProvider\ItemDataProviderInterface;
use ApiPlatform\Core\EventListener\EventPriorities;
use ApiPlatform\Core\Security\EventListener\DenyAccessListener;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @see https://github.com/api-platform/api-platform/issues/529
 * @see https://github.com/api-platform/api-platform/issues/709
 * @see https://github.com/api-platform/api-platform/issues/748
 */
final class RestaurantSubscriber implements EventSubscriberInterface
{
    private $itemDataProvider;
    private $denyAccessListener;

    public function __construct(
        ItemDataProviderInterface $itemDataProvider,
        DenyAccessListener $denyAccessListener
    ) {
        $this->itemDataProvider = $itemDataProvider;
        $this->denyAccessListener = $denyAccessListener;
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => ['accessControl', EventPriorities::PRE_READ],
        ];
    }

    public function accessControl(RequestEvent $event)
    {
        $request = $event->getRequest();

        if ('api_restaurants_orders_get_subresource' !== $request->attributes->get('_route')) {
            return;
        }

        $restaurant = $this->itemDataProvider->getItem(Restaurant::class, $request->attributes->get('id'), 'get');

        // Trick DenyAccessListener to make subresourceOperations work on parent resource
        $newRequest = $request->duplicate();
        $newRequest->attributes->set('data', $restaurant);
        $newRequest->attributes->set('_api_resource_class', Restaurant::class);
        $newRequest->attributes->set('_api_subresource_operation_name', 'orders_get_subresource');

        $newEvent = new RequestEvent($event->getKernel(), $newRequest, $event->getRequestType());

        $this->denyAccessListener->onKernelRequest($newEvent);
    }
}
