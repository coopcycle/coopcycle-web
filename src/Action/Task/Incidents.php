<?php

namespace AppBundle\Action\Task;

use Symfony\Component\HttpFoundation\Request;

class Incidents
{
    public function __invoke($data, Request $request)
    {
        return $data->getIncidents();
    }
}

