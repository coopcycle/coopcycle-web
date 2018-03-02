<?php

namespace AppBundle\Entity\Task;

use AppBundle\Entity\Task;

interface CollectionInterface
{
    public function getDistance();

    public function getDuration();

    public function getPolyline();

    public function getTasks();
}
