<?php

namespace AppBundle\Action\Task;

use AppBundle\Service\TaskManager;

class DeleteGroup
{
    public function __construct(TaskManager $taskManager)
    {
        $this->taskManager = $taskManager;
    }

    public function __invoke($data)
    {
        $this->taskManager->deleteGroup($data);

        return $data;
    }
}
