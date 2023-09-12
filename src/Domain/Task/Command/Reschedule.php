<?php

namespace AppBundle\Domain\Task\Command;

use AppBundle\Entity\Task;

class Reschedule
{
    public function __construct(
        private Task $task,
        private \DateTime $rescheduleAfter,
        private \DateTime $rescheduledBefore
    )
    { }

    public function getTask(): Task
    {
        return $this->task;
    }

    public function getRescheduleAfter(): \DateTime
    {
        return $this->rescheduleAfter;
    }

    public function getRescheduledBefore(): \DateTime
    {
        return $this->rescheduledBefore;
    }
}

