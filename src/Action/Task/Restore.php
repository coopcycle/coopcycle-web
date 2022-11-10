<?php

namespace AppBundle\Action\Task;

class Restore extends Base
{
    public function __invoke($data)
    {
        $this->taskManager->restore($data);

        return $data;
    }
}
