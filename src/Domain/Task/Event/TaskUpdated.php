<?php

namespace AppBundle\Domain\Task\Event;

use AppBundle\Domain\DomainEvent;
use AppBundle\Domain\SilentEventInterface;
use AppBundle\Domain\Task\Event;
use AppBundle\Entity\Task;

class TaskUpdated extends Event implements DomainEvent, SilentEventInterface
{
    public function __construct(
        Task $task,
    )
    {
        parent::__construct($task);
    }

    public static function messageName(): string
    {
        return 'task:updated';
    }
}
