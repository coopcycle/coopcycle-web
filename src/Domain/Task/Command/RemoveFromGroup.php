<?php

namespace AppBundle\Domain\Task\Command;

use AppBundle\Entity\Task;

class RemoveFromGroup
{
    private $task;

    public function __construct(Task $task)
    {
        $this->task = $task;
    }

    public function getTask()
    {
        return $this->task;
    }

}

