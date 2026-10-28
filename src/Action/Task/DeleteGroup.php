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
        // With ?keep_tasks=1, only the group is deleted, its tasks go back to unassigned
        $this->taskManager->deleteGroup($data, !$request->query->getBoolean('keep_tasks'));

        return $data;
    }
}
