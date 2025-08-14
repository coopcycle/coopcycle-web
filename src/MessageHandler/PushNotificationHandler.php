<?php

namespace AppBundle\MessageHandler;

use AppBundle\Message\PushNotification;
use AppBundle\Message\PushNotificationV2;
use AppBundle\Service\RemotePushNotificationManager;
use Nucleos\UserBundle\Model\UserManager as UserManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class PushNotificationHandler
{
    public function __construct(
        private readonly RemotePushNotificationManager $remotePushNotificationManager,
        private readonly UserManagerInterface $userManager)
    {
    }

    public function __invoke(PushNotification|PushNotificationV2 $message)
    {
        $users = [];
        // For `PushNotificationV2` we don't need to resolve users from usernames
        if ($message instanceof PushNotification) {
            $users = array_reduce($message->getUsers(), function ($carry, $item) {
                if ($user = $this->userManager->findUserByUsername($item)) {
                    $carry[] = $user;
                }

                return $carry;
            }, []);
        }

        $this->remotePushNotificationManager->send($message, $users);
    }
}
