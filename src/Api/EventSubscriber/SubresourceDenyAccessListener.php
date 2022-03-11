<?php

namespace AppBundle\Api\EventSubscriber;

use AppBundle\Entity\ApiApp;
use AppBundle\Entity\LocalBusiness;
use ApiPlatform\Core\DataProvider\ItemDataProviderInterface;
use ApiPlatform\Core\EventListener\EventPriorities;
use ApiPlatform\Core\Security\EventListener\DenyAccessListener;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use League\Bundle\OAuth2ServerBundle\Security\Authentication\Token\OAuth2Token;
use League\Bundle\OAuth2ServerBundle\Manager\AccessTokenManagerInterface;

/**
 * @see https://github.com/api-platform/api-platform/issues/529
 * @see https://github.com/api-platform/api-platform/issues/709
 * @see https://github.com/api-platform/api-platform/issues/748
 */
final class SubresourceDenyAccessListener implements EventSubscriberInterface
{
    private $itemDataProvider;
    private $doctrine;
    private $denyAccessListener;

    public function __construct(
        ItemDataProviderInterface $itemDataProvider,
        ManagerRegistry $doctrine,
        DenyAccessListener $denyAccessListener)
    {
        $this->itemDataProvider = $itemDataProvider;
        $this->doctrine = $doctrine;
        $this->denyAccessListener = $denyAccessListener;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => [
                [ 'security', 4 ],
            ],
        ];
    }

    private function supportsRequest(Request $request)
    {
        return in_array($request->attributes->get('_route'), [
            'api_restaurants_orders_get_subresource',
            'api_stores_deliveries_get_subresource',
            'api_tasks_events_get_subresource',
            'api_deliveries_put_item',
            'api_deliveries_pick_item',
            'api_deliveries_drop_item',
            'api_stores_get_item',
            'api_task_groups_get_item',
        ]);
    }

    public function security(RequestEvent $event)
    {
        $request = $event->getRequest();

        if (!$this->supportsRequest($request)) {
            return;
        }

        if ($request->attributes->has('_api_subresource_context')) {

            $subresourceContext = $request->attributes->get('_api_subresource_context');

            $parent = null;
            foreach ($subresourceContext['identifiers'] as $key => [$resourceClass, $id]) {
                if (null !== $parent = $this->itemDataProvider->getItem($resourceClass, $request->attributes->get($id), 'get')) {
                    break;
                }
            }

            if (null !== $parent) {

                $operationName = sprintf('%s_get_subresource', $subresourceContext['property']);
                $resourceClass = $this->doctrine->getManager()->getClassMetadata(get_class($parent))->name;

                // Trick DenyAccessListener to make subresourceOperations work on parent resource
                $newRequest = $request->duplicate();
                $newRequest->attributes->set('data', $parent);
                $newRequest->attributes->set('_api_resource_class', $resourceClass);
                $newRequest->attributes->set('_api_subresource_operation_name', $operationName);

                $newEvent = new RequestEvent($event->getKernel(), $newRequest, $event->getRequestType());

                $this->denyAccessListener->onSecurity($newEvent);
            }
        }
    }
}
