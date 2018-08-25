<?php

namespace AppBundle\Domain\Task;

use AppBundle\Domain\Event as BaseEvent;
use AppBundle\Entity\Task;
use SimpleBus\Message\Name\NamedMessage;

abstract class Event extends BaseEvent
{
    protected $task;

    public function __construct(Task $task)
    {
        $this->task = $task;
    }

    public function getTask(): Task
    {
        return $this->task;
    }
}
