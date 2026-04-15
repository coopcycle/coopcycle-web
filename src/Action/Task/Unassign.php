<?php

namespace AppBundle\Action\Task;

use AppBundle\Service\TaskManager;

class Unassign extends Base
{
    public function __construct(
        TaskManager $taskManager)
    {
        parent::__construct($taskManager);
    }

    public function __invoke($data)
    {
        $task = $data;

        if (!is_null($task->getDelivery())) {
            $task->getDelivery()->unassign();
        } else {
            $task->unassign();
        }

        return $task;
    }
}
