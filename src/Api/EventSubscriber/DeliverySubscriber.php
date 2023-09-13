<?php

namespace AppBundle\Api\EventSubscriber;

use AppBundle\Entity\Store;
use AppBundle\Message\DeliveryCreated;
use AppBundle\Security\TokenStoreExtractor;
use AppBundle\Service\DeliveryManager;
use Doctrine\Persistence\ManagerRegistry;
use ApiPlatform\Core\EventListener\EventPriorities;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class DeliverySubscriber implements EventSubscriberInterface
{
    private $doctrine;
    private $storeExtractor;

    private static $matchingRoutes = [
        'api_deliveries_get_item',
        'api_deliveries_post_collection',
        'api_deliveries_check_collection',
    ];

    public function __construct(
        ManagerRegistry $doctrine,
        TokenStoreExtractor $storeExtractor,
        DeliveryManager $deliveryManager)
    {
        $this->doctrine = $doctrine;
        $this->storeExtractor = $storeExtractor;
        $this->deliveryManager = $deliveryManager;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        // @see https://api-platform.com/docs/core/events/#built-in-event-listeners
        return [
            KernelEvents::VIEW => [
                ['setDefaults', EventPriorities::PRE_VALIDATE],
                ['handleCheckResponse', EventPriorities::POST_VALIDATE],
                ['addToStore', EventPriorities::POST_WRITE],
            ],
        ];
    }

    private function matchRoute(Request $request)
    {
        return in_array($request->attributes->get('_route'), self::$matchingRoutes);
    }

    public function setDefaults(ViewEvent $event)
    {
        $request = $event->getRequest();
        if (!$this->matchRoute($request)) {
            return;
        }

        $delivery = $event->getControllerResult();

        $this->deliveryManager->setDefaults($delivery);
    }

    public function addToStore(ViewEvent $event)
    {
        $request = $event->getRequest();

        if ('api_deliveries_post_collection' !== $request->attributes->get('_route')) {
            return;
        }

        $store = $this->storeExtractor->extractStore();

        if (null === $store) {
            return;
        }

        $delivery = $event->getControllerResult();

        $store->addDelivery($delivery);
        $this->doctrine->getManagerForClass(Store::class)->flush();
    }

    // FIXME
    // This is here to avoid the following issue when using write=false in the operation
    // Unable to generate an IRI for the item of type "AppBundle\Entity\Delivery"
    // We just respond HTTP 200, with an empty response
    // This may be remove once https://github.com/api-platform/core/pull/3150 is merged
    public function handleCheckResponse(ViewEvent $event)
    {
        $request = $event->getRequest();
        if ('api_deliveries_check_collection' !== $request->attributes->get('_route')) {
            return;
        }

        $event->setResponse(new JsonResponse([], 200));
    }
}
