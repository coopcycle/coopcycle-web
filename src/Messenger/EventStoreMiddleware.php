<?php

namespace AppBundle\Messenger;

use AppBundle\Domain\DomainEvent;
use AppBundle\Domain\EventStore;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;


class EventStoreMiddleware implements MiddlewareInterface
{

    public function __construct(private EventStore $eventStore)
    {}

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {   
        $message = $envelope->getMessage();
        if ($message instanceof DomainEvent) {
            $this->eventStore->addEvent($message);
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
