<?php

namespace AppBundle\MessageHandler;

use AppBundle\Message\PushNotification;
use AppBundle\Service\RemotePushNotificationManager;
use Nucleos\UserBundle\Model\UserManagerInterface;
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
        $users = array_reduce($message->getUsers(), function ($carry, $item) {
            if ($user = $this->userManager->findUserByUsername($item)) {
                $carry[] = $user;
            }

            return $carry;
        }, []);

        if (count($users) > 0) {
            $this->remotePushNotificationManager->send($message->getContent(), $users, $message->getData());
        }
    }
}
