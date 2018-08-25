<?php

namespace AppBundle\Domain\Task\Command;

use AppBundle\Entity\Task;

class MarkAsDone
{
    private $task;
    private $notes;

    public function __construct(Task $task, $notes = null)
    {
        $this->task = $task;
        $this->notes = $notes;
    }

    public function getTask()
    {
        return $this->task;
    }

    public function getNotes()
    {
        return $this->notes;
    }
}

