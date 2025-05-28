<?php

namespace AppBundle\MessageHandler\Tour;

use AppBundle\Domain\Tour\Event;
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
        $this->liveUpdates->toAdmins($event);
    }
}
