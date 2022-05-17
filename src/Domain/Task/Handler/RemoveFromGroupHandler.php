<?php

namespace AppBundle\Domain\Task\Handler;

use AppBundle\Domain\Task\Command\RemoveFromGroup;

class RemoveFromGroupHandler
{
    public function __invoke(RemoveFromGroup $command)
    {
        $task = $command->getTask();

        $task->setGroup(null);
    }
}
