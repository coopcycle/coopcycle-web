<?php

namespace AppBundle\Domain\Task\Handler;

use AppBundle\Domain\Task\Command\AddToGroup;
use AppBundle\Exception\TaskAlreadyGrouppedException;

class AddToGroupHandler
{
    public function __invoke(AddToGroup $command)
    {
        $task = $command->getTask();
        $group = $command->getTaskGroup();

        if (null !== $task->getGroup()) {
            throw new TaskAlreadyGrouppedException(sprintf('Task #%d is already in group #%d', $task->getId(), $task->getGroup()->getId()));
        }

        $task->setGroup($group);
    }
}
