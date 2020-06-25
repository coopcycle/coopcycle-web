<?php

namespace AppBundle\Entity\Task;

use AppBundle\Entity\Task;

/**
 * A Task\CollectionInterface is an ordered list of tasks.
 */
interface CollectionInterface
{
    /**
     * The distance (in meters) separating the first task from the last task,
     * going through all the geographical coordinates.
     *
     * @return int
     */
    public function getDistance();

    /**
     * The duration (in seconds) needed to go through all the geographical coordinates.
     *
     * @return int
     */
    public function getDuration();

    /**
     * The polyline to go through all the geographical coordinates.
     *
     * @return string
     */
    public function getPolyline();

    /**
     * The ordered tasks.
     *
     * @return array
     */
    public function getTasks();

    /**
     * @param int $distance
     */
    public function setDistance($distance);

    /**
     * @param int $duration
     */
    public function setDuration($duration);

    /**
     * @param string $polyline
     */
    public function setPolyline($polyline);
}
