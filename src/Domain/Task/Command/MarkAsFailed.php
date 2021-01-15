<?php

namespace AppBundle\Domain\Task\Command;

use AppBundle\Entity\Task;

class MarkAsFailed
{
    private $task;
    private $notes;
    private $contactName;

    public function __construct(Task $task, $notes = null, $contactName = null)
    {
        $this->task = $task;
        $this->notes = $notes;
        $this->contactName = $contactName;
    }

    public function getTask()
    {
        return $this->task;
    }

    public function getNotes()
    {
        return $this->notes;
    }

    public function getContactName()
    {
        return $this->contactName;
    }
}


