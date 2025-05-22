<?php

namespace AppBundle\MessageHandler\Task\Command;

use AppBundle\Message\Task\Command\RemoveFromGroup;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
class RemoveFromGroupHandler
{
    public function __invoke(RemoveFromGroup $command)
    {
        $task = $command->getTask();

        $task->setGroup(null);
    }
}
