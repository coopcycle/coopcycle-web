<?php

namespace AppBundle\Domain\Task\Event;

use AppBundle\Domain\Task\Event;

class TaskDone extends Event
{
    public static function messageName()
    {
        return 'task:done';
    }
}
