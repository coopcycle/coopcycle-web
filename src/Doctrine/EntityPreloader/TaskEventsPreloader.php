<?php

namespace AppBundle\Doctrine\EntityPreloader;

use AppBundle\Entity\Task;
use ShipMonk\DoctrineEntityPreloader\EntityPreloader;

/**
 * Loads the events of several tasks in one query.
 *
 * Task::addEvent() scans the task's events to avoid recording a duplicate,
 * which initialises the collection. Without this, a flush touching N tasks
 * issues N "SELECT ... FROM task_event WHERE task_id = ?" queries.
 */
class TaskEventsPreloader
{
    public function __construct(private EntityPreloader $preloader)
    {}

    /**
     * @param Task[] $tasks
     */
    public function preload(array $tasks): void
    {
        if (count($tasks) === 0) {
            return;
        }

        $this->preloader->preload(array_values($tasks), 'events');
    }
}
