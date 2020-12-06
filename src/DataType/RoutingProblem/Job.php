<?php

namespace AppBundle\DataType\RoutingProblem;

use AppBundle\Entity\Task;

/**
 * @see https://github.com/VROOM-Project/vroom/blob/master/docs/API.md#jobs
 */
class Job
{
    /**
     * an integer used as unique identifier
     * @var int
     */
    public $id;

    /**
     * coordinates array
     * @var array
     */
    public $location;

    /**
     * an array of time_window objects describing valid slots for job service start
     * @var array
     */
    public $time_windows;

    public static function fromTask(Task $task)
    {
        $job = new self();

        $job->id = $task->getId();
        $job->location = [
            $task->getAddress()->getGeo()->getLongitude(),
            $task->getAddress()->getGeo()->getLatitude()
        ];
        $job->time_windows = [
            [
                (int) $task->getAfter()->format('U'),
                (int) $task->getBefore()->format('U')
            ]
        ];

        return $job;
    }
}
