<?php

namespace AppBundle\Domain\Task\Reactor;

use ApiPlatform\Core\Api\IriConverterInterface;
use ApiPlatform\Core\Exception\InvalidArgumentException;
use AppBundle\Domain\Task\Event\TaskCreated;
use AppBundle\Security\UserManager;
use AppBundle\Service\RemotePushNotificationManager;

class SendRemotePushNotification
{
    private $remotePushNotificationManager;
    private $iriConverter;
    private $userManager;

    public function __construct(
        RemotePushNotificationManager $remotePushNotificationManager,
        IriConverterInterface $iriConverter,
        UserManager $userManager)
    {
        $this->remotePushNotificationManager = $remotePushNotificationManager;
        $this->iriConverter = $iriConverter;
        $this->userManager = $userManager;
    }

    public function __invoke($event)
    {
        /*
        if ($event instanceof TaskCreated) {

            $users = $this->userManager->findUsersByRole('ROLE_ADMIN');

            if (count($users) > 0) {

                try {
                    $payload = [
                        'event' => [
                            'name' => $event::messageName(),
                            'data' => [
                                'task' => $this->iriConverter->getIriFromItem($event->getTask()),
                            ]
                        ]
                    ];

                    // TODO Translate notification title
                    $this->remotePushNotificationManager
                        ->send('New task created', $users, $payload);
                } catch (InvalidArgumentException $e) {

                }

            }
        }
        */
    }
}
