<?php

namespace AppBundle\MessageHandler;

use AppBundle\Message\PushNotification;
use AppBundle\Service\RemotePushNotification;
use AppBundle\Service\RemotePushNotificationManager;
use Nucleos\UserBundle\Model\UserManager as UserManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Security\Core\User\UserInterface;

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
        /** @var UserInterface[] $users */
        $users = array_reduce($message->getUsers(), function ($carry, $item) {
            if ($user = $this->userManager->findUserByUsername($item)) {
                $carry[] = $user;
            }

            return $carry;
        }, []);

        if (count($users) > 0) {
            $this->remotePushNotificationManager->send(
                new RemotePushNotification(
                    $message->getTitle(),
                    $message->getBody(),
                    $message->getData()
                ),
                $users
            );
        }
    }
}
