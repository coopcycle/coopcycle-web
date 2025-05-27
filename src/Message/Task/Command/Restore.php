<?php

namespace AppBundle\Message\Task\Command;

use AppBundle\Entity\Task;

class Restore
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

