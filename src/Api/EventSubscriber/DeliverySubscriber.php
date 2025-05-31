<?php

namespace AppBundle\Api\EventSubscriber;

use AppBundle\Service\DeliveryManager;
use ApiPlatform\Symfony\EventListener\EventPriorities;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class DeliverySubscriber implements EventSubscriberInterface
{
    private static $matchingRoutes = [
        '_api_/deliveries/assert_post',
        '_api_/deliveries/suggest_optimizations_post',
    ];

    public function __construct(
        protected DeliveryManager $deliveryManager)
    {
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        // @see https://api-platform.com/docs/core/events/#built-in-event-listeners
        return [
            KernelEvents::REQUEST => [
                ['setDefaults', EventPriorities::POST_DESERIALIZE],
            ],
            KernelEvents::VIEW => [
                ['handleCheckResponse', EventPriorities::POST_VALIDATE],
            ],
        ];
    }

    private function matchRoute(Request $request)
    {
        return in_array($request->attributes->get('_route'), self::$matchingRoutes);
    }

    /**
     * After denormalizing the request, we may deduce missing data from the delivery's store or from the pickup.
     */
    public function setDefaults(RequestEvent $event)
    {
        $request = $event->getRequest();
        if (!$this->matchRoute($request)) {
            return;
        }

        $delivery = $request->attributes->get('data');

        $this->deliveryManager->setDefaults($delivery);
    }

    // FIXME
    // This is here to avoid the following issue when using write=false in the operation
    // Unable to generate an IRI for the item of type "AppBundle\Entity\Delivery"
    // We just respond HTTP 200, with an empty response
    // This may be remove once https://github.com/api-platform/core/pull/3150 is merged
    public function handleCheckResponse(ViewEvent $event)
    {
        $request = $event->getRequest();
        if ('_api_/deliveries/assert_post' !== $request->attributes->get('_route')) {
            return;
        }

        $event->setResponse(new JsonResponse([], 200));
    }
}
