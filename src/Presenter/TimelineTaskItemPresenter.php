<?php

namespace AppBundle\Presenter;

use AppBundle\Entity\TaskEvent;

class TimelineTaskItemPresenter
{
    public static function eventToColor(TaskEvent $taskEvent): string
    {
        return match($taskEvent->getName()) {
            'task:done' => 'green',
            'task:failed' => 'red',
            'task:cancelled' => 'red',
            'task:rescheduled' => 'orange',
            'task:incident-reported' => 'orange',
            default => 'blue'
        };
    }
}
