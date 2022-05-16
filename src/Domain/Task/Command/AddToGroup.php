<?php

namespace AppBundle\Domain\Task\Command;

use AppBundle\Entity\Task\Group as TaskGroup;

class AddToGroup
{
    private $tasks;
    private $taskGroup;

    public function __construct(array $tasks, TaskGroup $taskGroup)
    {
        $this->tasks = $tasks;
        $this->taskGroup = $taskGroup;
    }

    public function getTasks()
    {
        return $this->tasks;
    }

    public function getTaskGroup()
    {
        return $this->taskGroup;
    }

}

