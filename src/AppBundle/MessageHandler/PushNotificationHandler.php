<?php

namespace AppBundle\MessageHandler;

use AppBundle\Message\PushNotification;
use AppBundle\Service\RemotePushNotificationManager;
use FOS\UserBundle\Model\UserManagerInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class PushNotificationHandler implements MessageHandlerInterface
{
    public function __construct(
        RemotePushNotificationManager $remotePushNotificationManager,
        UserManagerInterface $userManager)
    {
        $this->remotePushNotificationManager = $remotePushNotificationManager;
        $this->userManager = $userManager;
    }

    public function __invoke(PushNotification $message)
    {
        $users = array_map(function ($username) {
            return $this->userManager->findUserByUsername($username);
        }, $message->getUsers());

        $this->remotePushNotificationManager->send($message->getContent(), $users, $message->getData());
    }
}
