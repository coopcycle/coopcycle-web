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
        // then this is the only task event we want to send to user/riders
        // we don't send it to admin/dispatchers because the app is listening to live update on this without any filter/condition on username so a rider who is an admin will receives updates for all riders
        // ref: https://github.com/coopcycle/coopcycle-app/blob/58d84b3519ccb16d53f8ae2948a211378f4929f7/src/redux/Courier/taskEntityReducer.js#L302
        // legacy event and new version of event
        // see https://github.com/coopcycle/coopcycle-app/issues/1803
        if ($event instanceof TaskListUpdated || $event instanceof TaskListUpdatedv2) {
            $user = $event->getTaskList()->getCourier();
            $this->liveUpdates->toUsers([ $user ], $event);
        } else {
            $this->liveUpdates->toAdmins($event);
        }
    }
}
