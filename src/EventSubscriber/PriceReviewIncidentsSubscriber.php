<?php

namespace AppBundle\EventSubscriber;

use AppBundle\Pricing\PriceReviewIncidents;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;

/**
 * Creates the incidents for the orders that were given a placeholder price,
 * once the work that priced them is over and they have been written.
 *
 * @see PriceReviewIncidents
 */
class PriceReviewIncidentsSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly PriceReviewIncidents $priceReviewIncidents)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::TERMINATE => 'onTerminate',
            ConsoleEvents::TERMINATE => 'onTerminate',
            WorkerMessageHandledEvent::class => 'onTerminate',
            // The message is retried, and the orders it priced were rolled back
            WorkerMessageFailedEvent::class => 'onFailure',
        ];
    }

    public function onTerminate(): void
    {
        $this->priceReviewIncidents->createPending();
    }

    public function onFailure(): void
    {
        $this->priceReviewIncidents->discardPending();
    }
}
