<?php

namespace AppBundle\Domain\Task\Reactor;

use AppBundle\Domain\Event;
use AppBundle\Domain\Task\Event\TaskAssigned;
use AppBundle\Domain\Task\Event\TaskCancelled;
use AppBundle\Domain\Task\Event\TaskCreated;
use AppBundle\Domain\Task\Event\TaskDone;
use AppBundle\Domain\Task\Event\TaskFailed;
use AppBundle\Domain\Task\Event\TaskListUpdated;
use AppBundle\Domain\Task\Event\TaskListUpdatedv2;
use AppBundle\Domain\Task\Event\TaskStarted;
use AppBundle\Domain\Task\Event\TaskUnassigned;
use AppBundle\Domain\Task\Event\TaskUpdated;
use AppBundle\Domain\Tour\Event\TourCreated;
use AppBundle\Domain\Tour\Event\TourUpdated;
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
        if ($event instanceof TaskListUpdated) {
            $user = $event->getCourier();
            $this->liveUpdates->toUsers([$user], $event);
        } else if ($event instanceof TaskAssigned
            || $event instanceof TaskCancelled
            || $event instanceof TaskCreated
            || $event instanceof TaskDone
            || $event instanceof TaskFailed
            || $event instanceof TaskStarted
            || $event instanceof TaskUnassigned
            || $event instanceof TaskUpdated
        ) {
            $this->liveUpdates->toRoles(['ROLE_ADMIN', 'ROLE_DISPATCHER'], $event);
        } else if ($event instanceof TaskListUpdatedv2) { // can be safely broadcasted to riders, dispatchers and admins
            $user = $event->getTaskList()->getCourier(); // not used in the rider part of the app yet
            $this->liveUpdates->toUsers([$user], $event); // not used in the rider part of the app yet
            $this->liveUpdates->toRoles(['ROLE_ADMIN', 'ROLE_DISPATCHER'], $event);
        } else if ($event instanceof TourCreated
            || $event instanceof TourUpdated
        ) {
            $this->liveUpdates->toRoles(['ROLE_ADMIN', 'ROLE_DISPATCHER'], $event);
        } else {
            $this->liveUpdates->toAdmins($event);
        }
    }
}
