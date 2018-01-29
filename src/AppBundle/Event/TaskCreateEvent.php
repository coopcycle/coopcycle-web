<?php

namespace AppBundle\Event;

use AppBundle\Entity\Task;
use FOS\UserBundle\Model\UserInterface;
use Symfony\Component\EventDispatcher\Event;

class TaskCreateEvent extends Event
{
    const NAME = 'task.create';

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
