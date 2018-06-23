<?php

namespace AppBundle\Controller\Utils;

trait WithRoutesTrait
{
    protected function withRoutes($params, $routes)
    {
        $routes = $routes ? $routes : [];
        $routes = array_merge($this->getRoutes(), $routes);

        $routeParams = [];
        foreach ($routes as $key => $value) {
            $routeParams[sprintf('%s_route', $key)] = $value;
        }

        return array_merge($params, $routeParams);
    }
}