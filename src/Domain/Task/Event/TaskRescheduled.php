<?php

namespace AppBundle\Domain\Task\Event;

use AppBundle\Domain\DomainEvent;
use AppBundle\Domain\HasIconInterface;
use AppBundle\Domain\Task\Event;
use AppBundle\Entity\Task;

class TaskRescheduled extends Event implements DomainEvent, HasIconInterface
{

    public function __construct(Task $task, private \DateTime $rescheduledAfter, private \DateTime $rescheduledBefore)
    {
        parent::__construct($task);
    }

    public function toPayload()
    {
        return [
            'rescheduled_after' => $this->rescheduledAfter,
            'rescheduled_before' => $this->rescheduledBefore,
        ];
    }

    public static function messageName(): string
    {
        return 'task:rescheduled';
    }

    public static function iconName()
    {
        return 'repeat';
    }
}
