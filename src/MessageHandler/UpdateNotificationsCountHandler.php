<?php

namespace AppBundle\MessageHandler;

use AppBundle\Message\UpdateNotificationsCount;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use AppBundle\Service\SocketIoManager;

class UpdateNotificationsCountHandler implements MessageHandlerInterface
{
    private $socketIoManager;

    public function __construct(
        SocketIoManager $socketIoManager)
    {
        $this->socketIoManager = $socketIoManager;
    }

    public function __invoke(UpdateNotificationsCount $message)
    {
        $payload = [
            'name' => 'notifications:count',
            'data' => $message->getCount(),
        ];

        $this->socketIoManager->publishEvent($message->getUsername(), $payload);
    }
}
