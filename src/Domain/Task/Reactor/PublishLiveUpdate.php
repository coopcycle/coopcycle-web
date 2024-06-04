<?php

namespace AppBundle\Domain\Task\Reactor;

use AppBundle\Domain\Event;
use AppBundle\Domain\Task\Event\TaskListUpdated;
use AppBundle\Domain\Task\Event\TaskListUpdatedv2;
use AppBundle\Service\LiveUpdates;

class PublishLiveUpdate
{
    private $liveUpdates;

    public function __construct(LiveUpdates $liveUpdates)
    {
        $this->liveUpdates = $liveUpdates;
    }

    public function __invoke(Event $event)
    {
        // this event is used to update the rider TaskList in the app
        // then this is the only task event we want to send to user/riders, not only to admin/dispatchers
        // legacy event and new version of event
        // see https://github.com/coopcycle/coopcycle-app/issues/1803
        if ($event instanceof TaskListUpdated || $event instanceof TaskListUpdatedv2) {
            $user = $event->getTaskList()->getCourier();
            $this->liveUpdates->toUsers([ $user ], $event);
        }
        $this->liveUpdates->toAdmins($event);
    }
}
