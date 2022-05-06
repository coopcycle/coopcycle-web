<?php

namespace AppBundle\Action\Task;

use AppBundle\Service\TaskManager;
use Doctrine\ORM\EntityManagerInterface;

class RemoveFromGroup extends Base
{
    public function __construct(
        TaskManager $taskManager,
        EntityManagerInterface $entityManager
    )
    {
        $this->taskManager = $taskManager;
        $this->entityManager = $entityManager;
    }

    public function __invoke($data)
    {
        $task = $data;

        $this->taskManager->removeFromGroup($task);

        // As we have configured write = false at operation level,
        // we have to flush changes here
        $this->entityManager->flush();

        return $task;
    }
}
