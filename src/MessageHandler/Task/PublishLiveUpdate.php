<?php

namespace AppBundle\MessageHandler\Task;

use AppBundle\Domain\Task\Event as TaskEvent;
use AppBundle\Domain\TaskList\Event as TaskListEvent;
use AppBundle\Domain\Tour\Event as TourEvent;
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
    
    public function __invoke(TaskEvent|TaskListEvent|TourEvent $event)
    {
        $this->liveUpdates->toAdmins($event);
    }
}
