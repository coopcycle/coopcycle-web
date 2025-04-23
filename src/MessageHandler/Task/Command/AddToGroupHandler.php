<?php

namespace AppBundle\MessageHandler\Task\Command;

use AppBundle\Message\Task\Command\AddToGroup;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
class AddToGroupHandler
{
    public function __invoke(AddToGroup $command)
    {
        $tasks = $command->getTasks();
        $group = $command->getTaskGroup();

        foreach ($tasks as $task) {
            $task->setGroup($group);
        }

    }
}
