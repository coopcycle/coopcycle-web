<?php

namespace AppBundle\Action\Task;

use AppBundle\Service\TaskManager;

class DeleteTour
{
    public function __construct(TaskManager $taskManager)
    {
        $this->taskManager = $taskManager;
    }

    public function __invoke($data)
    {
        foreach($data->getTasks() as $task) {
            $task->setTour(null);
        }

        return $data;
    }
}
