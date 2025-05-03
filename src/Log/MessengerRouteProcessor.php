<?php

namespace AppBundle\Log;

use AppBundle\Messenger\Stamp\RouteStamp;
use Monolog\Attribute\AsMonologProcessor;

/**
 * This processor adds the route and controller information to the log records coming from the Messenger.
 * Similarly to what @see \Symfony\Bridge\Monolog\Processor\RouteProcessor does for the web requests.
 */
#[AsMonologProcessor]
class MessengerRouteProcessor extends MessengerStampProcessor
{
    public function __invoke(array $record): array
    {
        $stamp = $this->getStamp();

        if ($stamp instanceof RouteStamp) {
            $record['extra']['requests'] = [
                [
                    'controller' => $stamp->getController(),
                    'route' => $stamp->getRoute()
                ]
            ];
        }

        return $record;
    }
}
