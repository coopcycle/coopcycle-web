<?php

namespace AppBundle\Messenger;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ConsumedByWorkerStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * This class is used to pass extra data to the Messenger/Worker (via a stamp)
 * for example, to enhance log messages created by the Messenger/Worker.
 */
abstract class StampMiddleware implements MiddlewareInterface
{
    private ?StampInterface $currentStamp = null;

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        // (2) Stamp consumption
        if ($stamp = $envelope->last($this->getStampFqcn())) {
            $this->setCurrentStamp($stamp);

            try {
                return $stack->next()->handle($envelope, $stack);
            } finally {
                $this->clearCurrentStamp($stamp);
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

    protected function setCurrentStamp(StampInterface $stamp): void
    {
        $this->currentStamp = $stamp;
    }

    protected function clearCurrentStamp(StampInterface $stamp): void
    {
        $this->currentStamp = null;
    }
}
