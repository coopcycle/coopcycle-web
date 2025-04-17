<?php

namespace AppBundle\Message\Task\Command;

use AppBundle\Entity\Task;

class ScanBarcode
{
    public function __construct(private Task $task)
    { }

    public function getTask(): Task
    {
        return $this->task;
    }

}
