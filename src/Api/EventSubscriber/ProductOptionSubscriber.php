<?php

namespace AppBundle\Api\EventSubscriber;

use Doctrine\ORM\EntityManagerInterface;
use ApiPlatform\Core\EventListener\EventPriorities;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class ProductOptionSubscriber implements EventSubscriberInterface
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => ['disableDoctrineFiter', EventPriorities::PRE_READ],
        ];
    }

    public function disableDoctrineFiter(RequestEvent $event)
    {
        $request = $event->getRequest();

        if ($request->attributes->get('_route') !== 'api_restaurants_product_options_get_subresource') {

            return;
        }

        $filterCollection = $this->entityManager->getFilters();
        if ($filterCollection->isEnabled('disabled_filter')) {
            $filterCollection->disable('disabled_filter');
        }
    }
}
