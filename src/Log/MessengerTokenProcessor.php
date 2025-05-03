<?php

namespace AppBundle\Log;

use AppBundle\Messenger\Stamp\RouteStamp;
use Monolog\Attribute\AsMonologProcessor;

/**
 * This processor adds user_identifier and user roles information to the log records coming from the Messenger.
 * Similarly to what @see \Symfony\Bridge\Monolog\Processor\TokenProcessor does for the web requests.
 */
#[AsMonologProcessor]
class MessengerTokenProcessor extends MessengerStampProcessor
{
    public function __invoke(array $record): array
    {
        $stamp = $this->getStamp();

        //TODO
//        if ($stamp instanceof RouteStamp) {
//            $record['extra']['requests'] = [
//                [
//                    'controller' => $stamp->getController(),
//                    'route' => $stamp->getRoute()
//                ]
//            ];
//        }

        return $record;
    }
}
