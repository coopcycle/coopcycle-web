<?php

namespace AppBundle\Domain\Task\Reactor;

use AppBundle\Domain\Event;
use AppBundle\Domain\Task\Event\TaskListUpdated;
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
        if ($event instanceof TaskListUpdated) {
            $user = $event->getTaskList()->getCourier();
            $this->socketIoManager->toUsers([ $user ], $event);
        } else {
            $this->socketIoManager->toAdmins($event);
        }
    }
}
