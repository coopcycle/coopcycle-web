<?php

namespace AppBundle\Domain\Task\Event;

use AppBundle\Domain\DomainEvent;
use AppBundle\Domain\HasIconInterface;
use AppBundle\Domain\Task\Event;
use AppBundle\Entity\Task;

class TaskStarted extends Event implements DomainEvent, HasIconInterface
{
    public function __construct(Task $task)
    {
        parent::__construct($task);
    }

    public static function messageName()
    {
        return 'task:started';
    }

    public static function iconName()
    {
        return 'play';
    }
}
