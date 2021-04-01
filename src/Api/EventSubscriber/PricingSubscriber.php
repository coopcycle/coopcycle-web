<?php

namespace AppBundle\Api\EventSubscriber;

use AppBundle\Api\Resource\Pricing as PricingResource;
use ApiPlatform\Core\EventListener\EventPriorities;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class PricingSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::VIEW => ['calculatePrice', EventPriorities::POST_VALIDATE],
        ];
    }

    public function calculatePrice(ViewEvent $event)
    {
        $request = $event->getRequest();

        $route = $request->attributes->get('_route');

        if ($route !== 'api_pricings_calc_price_collection') {
            return;
        }

        $result = $event->getControllerResult();

        if (!$result instanceof PricingResource) {
            return;
        }

        $event->setResponse(new JsonResponse($result->price));
    }
}
