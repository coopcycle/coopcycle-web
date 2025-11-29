<?php

namespace AppBundle\MessageHandler\Task;

use AppBundle\Domain\Task\Event as TaskEvent;
use AppBundle\Domain\Task\Event\TaskUpdated;
use AppBundle\Domain\TaskList\Event as TaskListEvent;
use AppBundle\Domain\Tour\Event as TourEvent;
use AppBundle\Service\LiveUpdates;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class PublishLiveUpdate
{
    private $liveUpdates;
    private $roles = ['ROLE_ADMIN','ROLE_DISPATCHER'];

    public function __construct(LiveUpdates $liveUpdates)
    {
        $this->liveUpdates = $liveUpdates;
    }
    
    public function __invoke(TaskEvent $event)
    {
        $user = null;

        if($event instanceof TaskUpdated) {
            $user = $event->getCourier();
        }

        if (is_null($user)) {
            $this->liveUpdates->toRoles($this->roles, $event);
        } else {
            $this->liveUpdates->toUserAndRoles($user, $this->roles, $event);
        }   
    }
}
