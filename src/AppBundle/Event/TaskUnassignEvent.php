<?php

namespace AppBundle\Event;

use AppBundle\Entity\Task;
use FOS\UserBundle\Model\UserInterface;
use Symfony\Component\EventDispatcher\Event;

class TaskUnassignEvent extends Event
{
    const NAME = 'task.unassign';

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
