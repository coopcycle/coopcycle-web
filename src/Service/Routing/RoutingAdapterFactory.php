<?php

namespace AppBundle\Service\Routing;

use AppBundle\Service\RoutingInterface;

class RoutingAdapterFactory
{
    public function __construct(
        private readonly OsrmWithFallback $osrmWithFallback,
        private readonly ValhallaWithFallback $valhallaWithFallback,
    ) {}

    /**
     * Pick the engine adapter based on the active configuration.
     */
    public function create(string $engine): RoutingInterface
    {
        return match (strtolower($engine)) {
            'valhalla' => $this->valhallaWithFallback,
            'osrm', '' => $this->osrmWithFallback,
            default => throw new \InvalidArgumentException(sprintf('Unknown routing engine "%s"', $engine)),
        };
    }
}
