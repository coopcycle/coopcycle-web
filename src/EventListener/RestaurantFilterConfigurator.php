<?php

namespace AppBundle\EventListener;

use AppBundle\Entity\LocalBusiness;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Doctrine\ORM\EntityManagerInterface;

class RestaurantFilterConfigurator
{
    protected $em;

    protected static $routes = [
        'homepage',
        'restaurants',
        'api_restaurants_get_collection',
        'shops',
        'restaurants_by_cuisine',
    ];

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function onKernelRequest(RequestEvent $event)
    {
        $request = $event->getRequest();

        if (!$request->attributes->has('_route')) {
            return;
        }

        $route = $request->attributes->get('_route');

        if (!in_array($route, self::$routes)) {
            return;
        }

        if ($this->em->getFilters()->isEnabled('disabled_filter')) {
            $filter = $this->em->getFilters()->getFilter('disabled_filter');
            $filter->add(LocalBusiness::class);
        }

    }
}
