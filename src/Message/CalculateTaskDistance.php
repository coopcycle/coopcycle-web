<?php

namespace AppBundle\Message;

class CalculateTaskDistance
{
    private $taskId;

    public function __construct($taskId)
    {
        $this->taskId = $taskId;
    }

    public function getTaskId()
    {
        return $this->taskId;
    }
}
