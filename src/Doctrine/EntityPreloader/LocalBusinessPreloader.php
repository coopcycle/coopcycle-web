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

            $this->preloader->preload([$entities], 'taxons');
            $menu = $entities->getMenuTaxon();

            if ($menu) {
                $this->preloader->preload([$menu], 'children');
                $children = $menu->getChildren()->toArray();
                $taxonProducts = $this->preloader->preload($children, 'taxonProducts');
                $products = $this->preloader->preload($taxonProducts, 'product');
                $this->preloader->preload($products, 'images');
            }

            return;
        }

        $promotions = $this->preloader->preload($entities, 'promotions');
        $this->preloader->preload($promotions, 'coupons');

        $this->preloader->preload($entities, 'preparationTimeRules');
        $this->preloader->preload($entities, 'fulfillmentMethods');
        $this->preloader->preload($entities, 'closingRules');
        $this->preloader->preload($entities, 'servesCuisine');
    }
}
