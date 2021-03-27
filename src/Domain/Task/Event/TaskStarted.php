<?php

namespace AppBundle\Domain\Task\Event;

use AppBundle\Domain\DomainEvent;
use AppBundle\Domain\HasIconInterface;
use AppBundle\Domain\Task\Event;
use AppBundle\Entity\Task;

class TaskStarted extends Event implements DomainEvent, HasIconInterface
{
    public static function messageName(): string
    {
        return 'task:started';
    }

    public static function iconName()
    {
        return 'play';
    }
}
