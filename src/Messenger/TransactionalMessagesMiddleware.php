<?php

declare(strict_types=1);

namespace AppBundle\Messenger;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Transport\Sender\SendersLocatorInterface;

/**
 * Hands the messages bound for a transport to TransactionalMessages while a
 * transaction runs. It must come first, so that the middlewares after it run
 * when the message is actually sent.
 */
class TransactionalMessagesMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly TransactionalMessages $transactionalMessages,
        private readonly SendersLocatorInterface $sendersLocator,
    )
    {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        if ($this->transactionalMessages->isHolding() && $this->isSentToTransport($envelope)) {
            $this->transactionalMessages->hold($envelope, $stack);

            return $envelope;
        }

        return $stack->next()->handle($envelope, $stack);
    }

    private function isSentToTransport(Envelope $envelope): bool
    {
        // Received from a transport: it is being handled, not sent
        if (!is_null($envelope->last(ReceivedStamp::class))) {
            return false;
        }

        foreach ($this->sendersLocator->getSenders($envelope) as $sender) {
            return true;
        }

        return false;
    }
}
