<?php

namespace AppBundle\Action\Task;

use AppBundle\Entity\Task\Group as TaskGroup;
use AppBundle\Service\TaskManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

class AddToGroup extends Base
{
    public function __construct(
        TaskManager $taskManager,
        EntityManagerInterface $objectManager
    )
    {
        $this->taskManager = $taskManager;
        $this->objectManager = $objectManager;
    }

    public function __invoke($data, Request $request)
    {
        $task = $data;

        $taskGroupId = $request->get('group_id');

        $taskGroup = $this->objectManager
            ->getRepository(TaskGroup::class)
            ->find($taskGroupId);

        $this->taskManager->addToGroup($task, $taskGroup);

        return $task;
    }
}
