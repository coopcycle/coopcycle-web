<?php

namespace AppBundle\MessageHandler;

use AppBundle\Message\UpdateNotificationsCount;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use AppBundle\Service\LiveUpdates;

#[AsMessageHandler]
class UpdateNotificationsCountHandler
{
    private $liveUpdates;

    public function __construct(
        LiveUpdates $liveUpdates)
    {
        $this->liveUpdates = $liveUpdates;
    }

    public function __invoke(UpdateNotificationsCount $message)
    {
        $payload = [
            'name' => 'notifications:count',
            'data' => $message->getCount(),
        ];

        $this->liveUpdates->publishEvent($message->getUsername(), $payload);
    }
}
