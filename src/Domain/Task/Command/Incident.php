<?php

namespace AppBundle\Domain\Task\Command;

use AppBundle\Entity\Task;

class Incident
{
    public function __construct(
        private Task $task,
        private string $reason,
        private ?string $notes = null,
        private array $data = []
   )
    { }

    public function getTask(): Task
    {
        return $this->task;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function getData(): array
    {
        return $this->data;
    }
}

