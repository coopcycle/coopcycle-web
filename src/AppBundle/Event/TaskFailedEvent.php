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
    protected $reason;

    public function __construct(Task $task, UserInterface $user, $reason = null)
    {
        $this->task = $task;
        $this->user = $user;
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

    public function getUser()
    {
        return $this->user;
    }
}
