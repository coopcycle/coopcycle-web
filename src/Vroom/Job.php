<?php

namespace AppBundle\Vroom;

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

    /**
     * @var string
     */
    public $description;
}
