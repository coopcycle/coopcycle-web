<?php

namespace AppBundle\Action\Task;

use Symfony\Component\HttpFoundation\Request;

class Incident extends Base
{
    public function __invoke($data, Request $request)
    {
        $this->taskManager->incident(
            $data,
            $this->getReason($request),
            $this->getNotes($request),
        );

        return $data;
    }
}
