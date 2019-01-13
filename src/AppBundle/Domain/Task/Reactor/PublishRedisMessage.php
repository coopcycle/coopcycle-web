<?php

namespace AppBundle\Domain\Task\Reactor;

use AppBundle\Domain\Task\Event;
use AppBundle\Service\SocketIoManager;

class PublishRedisMessage
{
    private $socketIoManager;

    public function __construct(SocketIoManager $socketIoManager)
    {
        $this->socketIoManager = $socketIoManager;
    }

    public function __invoke(Event $event)
    {
        try {
            $this->socketIoManager->toAdmins($event);
        } catch (\Exception $e) {

        }
    }
}
