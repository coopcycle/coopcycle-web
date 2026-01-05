<?php

namespace AppBundle\Log;

use AppBundle\Service\RequestContext;
use Monolog\Attribute\AsMonologProcessor;
use Monolog\LogRecord;

/**
 * This processor adds request context information to log records coming from the Messenger.
 */
#[AsMonologProcessor]
class MessengerRequestContextProcessor
{
    public function __construct(
        private readonly RequestContext $requestContext
    )
    {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        if ($requestId = $this->requestContext->getRequestId()) {
            $record['extra']['request_id'] = $requestId;
        }

        if ($route = $this->requestContext->getRoute()) {
            $record['extra']['requests'] = [
                [
                    'controller' => $this->requestContext->getController(),
                    'route' => $route
                ]
            ];
        }

        if ($clientIp = $this->requestContext->getClientIp()) {
            $record['extra']['client_ip'] = $clientIp;
        }

        if ($userAgent = $this->requestContext->getUserAgent()) {
            $record['extra']['user_agent'] = $userAgent;
        }

        if (!$record['extra']['token']) {
            $record['extra']['token'] = [];
        }

        if ($username = $this->requestContext->getUsername()) {
            $record['extra']['token']['user_identifier'] = $username;
        }

        if (!empty($this->requestContext->getRoles())) {
            $record['extra']['token']['roles'] = $this->requestContext->getRoles();
        }

        return $record;
    }
}
