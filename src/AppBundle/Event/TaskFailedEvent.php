<?php

namespace AppBundle\Event;

use AppBundle\Entity\Task;
use FOS\UserBundle\Model\UserInterface;
use Symfony\Component\EventDispatcher\Event;

class TaskFailedEvent extends Event
{
    const NAME = 'task.failed';

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

    public function getNotes()
    {
        return $this->notes;
    }

    public function getUser()
    {
        return $this->user;
    }
}
