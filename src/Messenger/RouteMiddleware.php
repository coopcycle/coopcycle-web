<?php

namespace AppBundle\Messenger;

use AppBundle\Log\MessengerRouteProcessor;
use AppBundle\Messenger\Stamp\RouteStamp;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\Stamp\StampInterface;

class RouteMiddleware extends StampMiddleware
{
    public function __construct(
        private readonly RequestStack $requestStack,
        MessengerRouteProcessor $stampProcessor,
    )
    {
        parent::__construct($stampProcessor);
    }

    protected function getStampFqcn(): string
    {
        return RouteStamp::class;
    }

    protected function createStamp(): ?StampInterface
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request) {
            $route = $request->attributes->get('_route');
            $controller = $request->attributes->get('_controller');

            if (null !== $route && null !== $controller) {
                return new RouteStamp($route, $controller);
            } else {
                return null;
            }

        } else {
            return null;
        }
    }
}
