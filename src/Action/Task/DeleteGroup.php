<?php

namespace AppBundle\Action\Task;

use AppBundle\Service\TaskManager;
use Symfony\Component\HttpFoundation\Request;

class DeleteGroup
{
    public $taskManager;
    public function __construct(TaskManager $taskManager)
    {
        $this->taskManager = $taskManager;
    }

    public function __invoke($data, Request $request)
    {
        // Tasks are only cancelled with ?cancel_tasks=1, otherwise they go back to unassigned
        $this->taskManager->deleteGroup($data, $request->query->getBoolean('cancel_tasks'));

        return $data;
    }
}
