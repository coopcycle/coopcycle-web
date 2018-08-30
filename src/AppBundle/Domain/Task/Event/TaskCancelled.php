<?php

namespace AppBundle\Domain\Task\Event;

use AppBundle\Domain\DomainEvent;
use AppBundle\Domain\Task\Event;

class TaskCancelled extends Event implements DomainEvent
{
    public static function messageName()
    {
        return 'task:cancelled';
    }
}

