<?php

namespace AppBundle\Domain\Middleware;

use AppBundle\Domain\DomainEvent;
use AppBundle\Domain\EventStore;
use SimpleBus\Message\Bus\Middleware\MessageBusMiddleware;

/**
 * @see http://docs.simplebus.io/en/latest/Guides/event_bus.html#implementing-your-own-event-bus-middleware
 */
class EventStoreMiddleware implements MessageBusMiddleware
{
    private $eventStore;

    public function __construct(EventStore $eventStore)
    {
        $this->eventStore = $eventStore;
    }

    public function handle($message, callable $next): void
    {
        if ($message instanceof DomainEvent) {
            $this->eventStore->addEvent($message);
        }

        $next($message);
    }
}
