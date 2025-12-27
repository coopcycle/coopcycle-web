<?php

namespace AppBundle\Log;

use AppBundle\Messenger\Stamp\RequestContextStamp;
use Monolog\Attribute\AsMonologProcessor;
use Monolog\LogRecord;

/**
 * This processor adds request context information to log records coming from the Messenger.
 */
#[AsMonologProcessor]
class MessengerRequestContextProcessor extends MessengerStampProcessor
{
    public function __invoke(LogRecord $record): LogRecord
    {
        //FIXME: get from RequestContext service instead of stamp?
        $stamp = $this->getStamp();

        if ($stamp instanceof RequestContextStamp) {

            if ($requestId = $stamp->getRequestId()) {
                $record['extra']['request_id'] = $requestId;
            }

            if ($route = $stamp->getRoute()) {
                $record['extra']['requests'] = [
                    [
                        'controller' => $stamp->getController(),
                        'route' => $route
                    ]
                ];
            }

            if ($clientIp = $stamp->getClientIp()) {
                $record['extra']['client_ip'] = $clientIp;
            }

            if ($userAgent = $stamp->getUserAgent()) {
                $record['extra']['user_agent'] = $userAgent;
            }

            if (!$record['extra']['token']) {
                $record['extra']['token'] = [];
            }

            if ($username = $stamp->getUsername()) {
                $record['extra']['token']['user_identifier'] = $username;
            }

            if (!empty($stamp->getRoles())) {
                $record['extra']['token']['roles'] = $stamp->getRoles();
            }
        }

        return $record;
    }
}

