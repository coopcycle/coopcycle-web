<?php

namespace AppBundle\Action\Task;

class Cancel extends Base
{
    public function __invoke($data)
    {
        $this->taskManager->cancel($data);

        return $data;
    }
}
