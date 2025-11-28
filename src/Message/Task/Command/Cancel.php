<?php

namespace AppBundle\Message\Task\Command;

use AppBundle\Entity\Task;

class Cancel
{
    /**
     * @param Task[] $tasks
     * @param bool $recalculatePrice Whether to recalculate order price after task cancellation
     */
    public function __construct(
        private readonly array $tasks,
        private readonly bool $recalculatePrice = false
    )
    {
    }

    /**
     * @return Task[]
     */
    public function getTasks(): array
    {
        return $this->tasks;
    }

    public function shouldRecalculatePrice(): bool
    {
        return $this->recalculatePrice;
    }
}
