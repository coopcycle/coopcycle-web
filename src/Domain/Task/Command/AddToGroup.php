<?php

namespace AppBundle\Domain\Task\Command;

use AppBundle\Entity\Task;
use AppBundle\Entity\Task\Group as TaskGroup;

class AddToGroup
{
    private $task;
    private $taskGroup;

    public function __construct(Task $task, TaskGroup $taskGroup)
    {
        $this->task = $task;
        $this->taskGroup = $taskGroup;
    }

    public function getTask()
    {
        return $this->task;
    }

    public function getTaskGroup()
    {
        return $this->taskGroup;
    }

}

