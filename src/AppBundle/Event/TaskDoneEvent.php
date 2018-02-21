<?php

namespace AppBundle\Event;

use AppBundle\Entity\Task;
use FOS\UserBundle\Model\UserInterface;
use Symfony\Component\EventDispatcher\Event;

class TaskDoneEvent extends Event
{
    const NAME = 'task.done';

    protected $task;
    protected $user;
    protected $notes;

    public function __construct(Task $task, UserInterface $user, $notes = null)
    {
        $this->task = $task;
        $this->user = $user;
        $this->notes = $notes;
    }

    public function getTask()
    {
        return $this->task;
    }

    public function getUser()
    {
        return $this->user;
    }

    public function getNotes()
    {
        return $this->notes;
    }
}
