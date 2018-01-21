<?php

namespace AppBundle\Event;

use AppBundle\Entity\Task;
use Symfony\Component\EventDispatcher\Event;

class TaskDoneEvent extends Event
{
    const NAME = 'task.done';

    protected $task;

    public function __construct(Task $task)
    {
        $this->task = $task;
    }

    public function getTask()
    {
        return $this->task;
    }
}
