<?php

namespace AppBundle\Messenger\Stamp;

use Symfony\Component\Messenger\Stamp\StampInterface;

class RouteStamp implements StampInterface
{
    public function __construct(
        private readonly string $route,
        private readonly string $controller,
    ) {
    }

    public function getRoute(): string
    {
        return $this->route;
    }

    public function getController(): string
    {
        return $this->controller;
    }
}
