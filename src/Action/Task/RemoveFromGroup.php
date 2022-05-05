<?php

namespace AppBundle\Action\Task;

use AppBundle\Service\TaskManager;

class RemoveFromGroup extends Base
{
    public function __construct(
        TaskManager $taskManager
    )
    {
        $this->taskManager = $taskManager;
    }

    public function __invoke($data)
    {
        $task = $data;

        $this->taskManager->removeFromGroup($task);

        return $task;
    }
}
