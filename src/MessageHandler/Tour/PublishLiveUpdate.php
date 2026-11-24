<?php

namespace AppBundle\MessageHandler\Tour;

use AppBundle\Domain\Tour\Event;
use AppBundle\Service\LiveUpdates;
use AppBundle\Service\LiveUpdatesSuspension;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class PublishLiveUpdate
{
    private $liveUpdates;

    public function __construct(LiveUpdates $liveUpdates, private LiveUpdatesSuspension $suspension)
    {
        $this->liveUpdates = $liveUpdates;
    }
    
    public function __invoke(Event $event)
    {
        // A job saving a lot of tours sends a single event instead
        if ($this->suspension->isSuspended()) {
            return;
        }

        $this->liveUpdates->toDispatchers($event);
    }
}
