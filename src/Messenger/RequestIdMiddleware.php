<?php

namespace AppBundle\Messenger;

use AppBundle\Log\MessengerRequestIdProcessor;
use AppBundle\Messenger\Stamp\RequestIdStamp;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ConsumedByWorkerStamp;

class RequestIdMiddleware implements MiddlewareInterface
{
    private ?RequestIdStamp $currentRequestIdStamp = null;

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly MessengerRequestIdProcessor $messengerRequestIdProcessor
    ) {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        if ($stamp = $envelope->last(RequestIdStamp::class)) {
            $this->messengerRequestIdProcessor->setStamp($stamp);
            $this->currentRequestIdStamp = $stamp;

            try {
                return $stack->next()->handle($envelope, $stack);
            } finally {
                $this->messengerRequestIdProcessor->setStamp(null);
                $this->currentRequestIdStamp = null;
            }
        }

        $request = $this->requestStack->getCurrentRequest();
        if (! $envelope->last(ConsumedByWorkerStamp::class) && $request && $request->headers->has('X-Request-ID')) {
            $envelope = $envelope->with(new RequestIdStamp($request->headers->get('X-Request-ID')));
        } elseif (! $envelope->last(ConsumedByWorkerStamp::class) && $this->currentRequestIdStamp !== null) {
            $envelope = $envelope->with($this->currentRequestIdStamp);
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
