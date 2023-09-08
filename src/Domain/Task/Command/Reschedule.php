<?php

namespace AppBundle\Domain\Task\Command;

use AppBundle\Entity\Task;

class Reschedule
{
    public function __construct(private Task $task, private \DateTime $rescheduleDateTime)
    { }

    public function getTask()
    {
        return $this->task;
    }

    public function getRescheduleDateTime(): \DateTime
    {
        return $this->rescheduleDateTime;
    }
}

