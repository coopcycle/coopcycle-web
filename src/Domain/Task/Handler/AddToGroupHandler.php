<?php

namespace AppBundle\Domain\Task\Handler;

use AppBundle\Domain\Task\Command\AddToGroup;
use AppBundle\Exception\TaskAlreadyBelongsToGroupException;

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
