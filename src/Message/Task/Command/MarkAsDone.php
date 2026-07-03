<?php

namespace AppBundle\Message\Task\Command;

use AppBundle\Entity\Task;

class MarkAsDone
{

    public function __construct(
        private Task $task,
        private $notes = null,
        private $contactName = null,
        private bool $calculateCO2 = true
    )
    { }

    public function getTask(): Task
    {
        return $this->task;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function getContactName(): ?string
    {
        return $this->contactName;
    }

    public function getCalculateCO2(): bool
    {
        return $this->calculateCO2;
    }
}
