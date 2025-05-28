<?php

namespace AppBundle\Message\Task\Command;

use AppBundle\Entity\Task\Group as TaskGroup;

class DeleteGroup
{
    private $taskGroup;

    public function __construct(TaskGroup $taskGroup)
    {
        $this->taskGroup = $taskGroup;
    }

    public function getTaskGroup()
    {
        return $this->taskGroup;
    }
}

