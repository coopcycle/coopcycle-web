<?php

namespace AppBundle\Message\Task\Command;

use AppBundle\Entity\Task;

class Cancel
{
    /**
     * @param Task[] $tasks
     */
    public function __construct(private readonly array $tasks)
    {
    }

    /**
     * @return Task[]
     */
    public function getTasks(): array
    {
        return $this->tasks;
    }
}
