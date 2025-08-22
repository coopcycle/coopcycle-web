<?php

namespace AppBundle\MessageHandler;

use AppBundle\Message\PushNotification;
use AppBundle\Service\RemotePushNotificationManager;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class PushNotificationHandler
{
    public function __construct(
        private readonly RemotePushNotificationManager $remotePushNotificationManager)
    {
    }

    public function __invoke(PushNotification $message)
    {
        $this->remotePushNotificationManager->send($message);
    }
}
