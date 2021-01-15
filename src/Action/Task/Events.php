<?php

namespace AppBundle\Action\Task;

use Symfony\Component\HttpFoundation\Request;

class Events
{
    public function __invoke($data, Request $request)
    {
        return $data->getEvents();
    }
}
