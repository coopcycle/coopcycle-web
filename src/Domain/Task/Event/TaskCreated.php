<?php

namespace AppBundle\Domain\Task\Event;

use AppBundle\Domain\DomainEvent;
use AppBundle\Domain\HasIconInterface;
use AppBundle\Domain\Task\Event;

class TaskCreated extends Event implements DomainEvent, HasIconInterface
{
    public static function messageName(): string
    {
        return 'task:created';
    }

    public static function iconName()
    {
        return 'plus';
    }
}

