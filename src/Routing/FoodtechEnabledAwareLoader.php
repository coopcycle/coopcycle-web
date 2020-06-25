<?php

namespace AppBundle\Routing;

use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class FoodtechEnabledAwareLoader extends Loader
{
	private $isFoodtechEnabled;
    private $isLoaded = false;

    public function __construct($isFoodtechEnabled = true)
    {
    	$this->isFoodtechEnabled = $isFoodtechEnabled;
    }

    public function load($resource, $type = null)
    {
        if (true === $this->isLoaded) {
            throw new \RuntimeException('Do not add the "foodtech_enabled_aware" loader twice');
        }

        $routes = new RouteCollection();

        $type = 'yaml';

        if ($this->isFoodtechEnabled) {
        	$dynamicRoutes = $this->import('@AppBundle/Resources/config/routing/foodtech.yml', $type);
        } else {
        	$dynamicRoutes = $this->import('@AppBundle/Resources/config/routing/lastmile.yml', $type);
        }

        $routes->addCollection($dynamicRoutes);

        $this->isLoaded = true;

        return $routes;
    }

    public function supports($resource, $type = null)
    {
        return 'foodtech_enabled_aware' === $type;
    }
}
