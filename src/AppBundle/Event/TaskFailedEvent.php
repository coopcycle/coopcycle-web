<?php

namespace AppBundle\Event;

use AppBundle\Entity\Task;
use Symfony\Component\EventDispatcher\Event;

class TaskFailedEvent extends Event
{
    const NAME = 'task.failed';

    protected $task;
    protected $reason;

    public function __construct(Task $task, $reason = null)
    {
        $this->task = $task;
        $this->reason = $reason;
    }

    public function getTask()
    {
        return $this->task;
    }

    public function getReason()
    {
        return $this->reason;
    }
}
