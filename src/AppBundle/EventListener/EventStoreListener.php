<?php

namespace AppBundle\EventListener;

use AppBundle\Domain\EventStore;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Event\PostResponseEvent;

class EventStoreListener
{
    private $eventStore;
    private $doctrine;
    private $logger;

    public function __construct(EventStore $eventStore, $doctrine, LoggerInterface $logger)
    {
        $this->eventStore = $eventStore;
        $this->doctrine = $doctrine;
        $this->logger = $logger;
    }

    public function onKernelTerminate(PostResponseEvent $event)
    {
        if (0 === count($this->eventStore)) {
            return;
        }

        $this->logger->debug(sprintf('EventStore contains %d events', count($this->eventStore)));

        $classes = [];
        foreach ($this->eventStore as $event) {
            $classes[] = get_class($event);
            $this->doctrine->getManagerForClass(get_class($event))->persist($event);
        }

        // Call clear() before flush() to avoid an infinite loop
        $this->eventStore->clear();

        foreach (array_unique($classes) as $class) {
            $this->doctrine->getManagerForClass($class)->flush();
        }
    }
}
