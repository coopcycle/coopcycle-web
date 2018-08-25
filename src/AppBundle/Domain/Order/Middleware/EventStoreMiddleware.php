<?php

namespace AppBundle\Domain\Order\Middleware;

use AppBundle\Domain\DomainEvent;
use AppBundle\Domain\Order\EventStore;
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

    public function handle($message, callable $next)
    {
        if ($message instanceof DomainEvent) {
            $this->eventStore->add($message);
        }

        $next($message);
    }
}
