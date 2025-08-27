<?php

namespace AppBundle\MessageHandler;

use AppBundle\Message\PushNotification;
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

    public function __invoke(PushNotification $message)
    {
        $users = array_reduce($message->getUsers(), function ($carry, $item) {
            if ($user = $this->userManager->findUserByUsername($item)) {
                $carry[] = $user;
            }

            return $carry;
        }, []);

        if (count($users) > 0) {
            $this->remotePushNotificationManager->send(
                new PushNotification(
                    $message->getTitle(),
                    $message->getBody(),
                    $users,
                    $message->getData()
                ),
                $users
            );
        }
    }
}
