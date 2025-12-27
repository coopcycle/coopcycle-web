<?php

namespace AppBundle\Messenger;

use AppBundle\Log\MessengerRequestContextProcessor;
use AppBundle\Messenger\Stamp\RequestContextStamp;
use AppBundle\Service\RequestContext;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\StampInterface;

class RequestContextMiddleware extends StampMiddleware
{
    public function __construct(
        private readonly RequestContext $requestContext,
        MessengerRequestContextProcessor $stampProcessor,
    )
    {
        parent::__construct($stampProcessor);
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        // Check if stamp exists and set context from it
        $stamp = $envelope->last($this->getStampFqcn());
        if ($stamp instanceof RequestContextStamp) {
            $this->requestContext->setFromStamp(
                $stamp->getRequestId(),
                $stamp->getRoute(),
                $stamp->getController(),
                $stamp->getClientIp(),
                $stamp->getUserAgent(),
                $stamp->getUsername(),
                $stamp->getRoles()
            );
        }

        try {
            // Call parent to handle stamp attachment/consumption and propagation
            return parent::handle($envelope, $stack);
        } finally {
            // Clear context after processing to avoid leaking between messages
            if ($stamp instanceof RequestContextStamp) {
                $this->requestContext->clear();
            }
        }
    }

    protected function getStampFqcn(): string
    {
        return RequestContextStamp::class;
    }

    protected function createStamp(): ?StampInterface
    {
        $requestId = $this->requestContext->getRequestId();
        $route = $this->requestContext->getRoute();
        $controller = $this->requestContext->getController();
        $clientIp = $this->requestContext->getClientIp();
        $userAgent = $this->requestContext->getUserAgent();
        $username = $this->requestContext->getUsername();
        $roles = $this->requestContext->getRoles();

        // Only create stamp if we have at least some data
        if ($requestId || $route || $controller || $clientIp || $userAgent || $username || !empty($roles)) {
            return new RequestContextStamp(
                $requestId,
                $route,
                $controller,
                $clientIp,
                $userAgent,
                $username,
                $roles
            );
        }

        return null;
    }
}

