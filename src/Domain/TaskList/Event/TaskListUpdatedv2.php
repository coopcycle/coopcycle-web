<?php

namespace AppBundle\Domain\TaskList\Event;

use AppBundle\Domain\TaskList\Event as BaseEvent;
use AppBundle\Domain\SerializableEventInterface;

class TaskListUpdatedv2 extends BaseEvent implements SerializableEventInterface
{
    public static function messageName(): string
    {
        return 'v2:task_list:updated';
    }
}
