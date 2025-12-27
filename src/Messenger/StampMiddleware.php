<?php

namespace AppBundle\Messenger;

use AppBundle\Log\MessengerStampProcessor;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ConsumedByWorkerStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * This class is used to add an extra data to a message (via a stamp)
 * that is later on added to log messages created by the Messenger component.
 */
abstract class StampMiddleware implements MiddlewareInterface
{
    private ?StampInterface $currentStamp = null;

    public function __construct(
        private readonly MessengerStampProcessor $messengerStampProcessor
    )
    {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        // (2) Stamp consumption: If a stamp exists, set it on the MessengerStampProcessor (monolog processor)
        if ($stamp = $envelope->last($this->getStampFqcn())) {
            $this->messengerStampProcessor->setStamp($stamp);
            $this->currentStamp = $stamp;

            try {
                return $stack->next()->handle($envelope, $stack);
            } finally {
                $this->messengerStampProcessor->setStamp(null);
                $this->currentStamp = null;
            }
        }

        // (1) Stamp attachment: Attach a stamp to the message envelope if not already present and not consumed by worker
        if (!$envelope->last(ConsumedByWorkerStamp::class) && $stamp = $this->createStamp()) {
            $envelope = $envelope->with($stamp);
        } elseif (!$envelope->last(ConsumedByWorkerStamp::class) && $this->currentStamp !== null) {
            $envelope = $envelope->with($this->currentStamp);
        }

        return $stack->next()->handle($envelope, $stack);
    }

    abstract protected function getStampFqcn(): string;

    abstract protected function createStamp(): ?StampInterface;
}
