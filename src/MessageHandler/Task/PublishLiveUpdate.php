<?php

namespace AppBundle\MessageHandler\Task;

use AppBundle\Domain\Task\Event as TaskEvent;
use AppBundle\Domain\Task\Event\TaskUpdated;
use AppBundle\Service\LiveUpdates;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Publishes task events, like the Order, Tour and TaskList reactors do.
 *
 * This used to enqueue an async message carrying the task *id*, which a worker
 * read back before publishing. That made task the only aggregate whose live
 * update was derived from a second read, and the read could land before the
 * change the event describes had been written -- producing `task:done` payloads
 * carrying `status=TODO`.
 *
 * The event already holds the task, so the payload is built here, from the
 * entity in memory. The Centrifugo call is still off the request thread: it is
 * LiveUpdates that defers it, uniformly for every aggregate.
 */
#[AsMessageHandler]
class PublishLiveUpdate
{
    public function __construct(private LiveUpdates $liveUpdates)
    {}

    public function __invoke(TaskEvent $event)
    {
        // A task update is also sent to the courier it is assigned to, so the
        // rider app sees changes to its own task without being a dispatcher.
        if ($event instanceof TaskUpdated) {
            $courier = $event->getTask()->getAssignedCourier();

            if (null !== $courier) {
                $this->liveUpdates->toUserAndDispatchers($courier, $event);

                return;
            }
        }

        $this->liveUpdates->toDispatchers($event);
    }
}
