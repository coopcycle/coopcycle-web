<?php

namespace AppBundle\Doctrine\EntityPreloader;

use ShipMonk\DoctrineEntityPreloader\EntityPreloader;

final class LocalBusinessPreloader
{
    public function __construct(private EntityPreloader $preloader)
    {}

    public function preload($entities)
    {
        if (!is_array($entities)) {
            $entities = [ $entities ];
        }

        $promotions = $this->preloader->preload($entities, 'promotions');
        $this->preloader->preload($promotions, 'coupons');

        $this->preloader->preload($entities, 'preparationTimeRules');
        $this->preloader->preload($entities, 'fulfillmentMethods');
        $this->preloader->preload($entities, 'closingRules');
        $this->preloader->preload($entities, 'servesCuisine');
    }
}
