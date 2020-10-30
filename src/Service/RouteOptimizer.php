<?php

namespace AppBundle\Service;

class RouteOptimizer
{
    const VROOM_ENDPOINT = "localhost:3000";
    private $tasks;
    private $vehicles;
    private $normalizer;

    public function _constructor(array $tasks)
    {
        $this->tasks = $tasks;
    }

    public function optimize()
    {
        // TODO - convert tasks to jobs
        // TODO - convert vehicles

    }

    private function sendVroomRequest($action)
    {

    }
}

?>
