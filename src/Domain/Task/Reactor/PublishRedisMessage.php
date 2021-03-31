<?php

namespace AppBundle\Domain\Task\Reactor;

use AppBundle\Domain\Event;
use AppBundle\Domain\Task\Event\TaskListUpdated;
use AppBundle\Service\LiveUpdates;

class PublishRedisMessage
{
    private $liveUpdates;

    public function __construct(LiveUpdates $liveUpdates)
    {
        $this->liveUpdates = $liveUpdates;
    }

    public function __invoke(Event $event)
    {
        if ($event instanceof TaskListUpdated) {
            $user = $event->getTaskList()->getCourier();
            $this->liveUpdates->toUsers([ $user ], $event);
        } else {
            $this->liveUpdates->toAdmins($event);
        }
    }
}
