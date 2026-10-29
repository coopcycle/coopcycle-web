<?php

namespace AppBundle\Message\Task\Command;

use AppBundle\Entity\Task\Group as TaskGroup;

class DeleteGroup
{
    private $taskGroup;
    private $cancelTasks;

    public function __construct(TaskGroup $taskGroup, bool $cancelTasks = true)
    {
        $this->taskGroup = $taskGroup;
        $this->cancelTasks = $cancelTasks;
    }

    public function getTaskGroup()
    {
        return $this->taskGroup;
    }

    public function shouldCancelTasks(): bool
    {
        return $this->cancelTasks;
    }
}
