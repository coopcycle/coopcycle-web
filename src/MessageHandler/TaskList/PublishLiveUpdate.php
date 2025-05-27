<?php

namespace AppBundle\MessageHandler\TaskList;

use AppBundle\Domain\TaskList\Event;
use AppBundle\Domain\TaskList\Event\TaskListUpdated;
use AppBundle\Domain\TaskList\Event\TaskListUpdatedv2;
use AppBundle\Service\LiveUpdates;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class PublishLiveUpdate
{
    private $liveUpdates;

    public function __construct(LiveUpdates $liveUpdates)
    {
        $this->liveUpdates = $liveUpdates;
    }
    
    public function __invoke(Event $event)
    {
        // this event is used to update the rider's TaskList in the app
        // then this is the only task event we want to send to user/riders
        // we don't send it to admin/dispatchers because the app is listening to live update on this without any filter/condition on username so a rider who is an admin will receives updates for all riders
        // ref: https://github.com/coopcycle/coopcycle-app/blob/58d84b3519ccb16d53f8ae2948a211378f4929f7/src/redux/Courier/taskEntityReducer.js#L302
        // legacy event and new version of event
        // see https://github.com/coopcycle/coopcycle-app/issues/1803
        if ($event instanceof TaskListUpdated) {
            $user = $event->getCourier();
            $this->liveUpdates->toUsers([$user], $event);
        } else if ($event instanceof TaskListUpdatedv2) {
            $user = $event->getTaskList()->getCourier(); // Not used in the rider part of the app yet
            $this->liveUpdates->toUserAndRoles($user, ['ROLE_ADMIN', 'ROLE_DISPATCHER'], $event);
        } else {
            // Can be safely broadcasted both to admins and dispatchers
            $this->liveUpdates->toRoles(['ROLE_ADMIN', 'ROLE_DISPATCHER'], $event);
        }
    }
}
