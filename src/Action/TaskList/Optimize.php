<?php

namespace AppBundle\Action\TaskList;

use AppBundle\Entity\RoutingProblem;
use AppBundle\Entity\Vehicle;
use AppBundle\Entity\TaskList;
use AppBundle\Service\RouteOptimizer;

final class Optimize
{
    private $optimizer;

    public function __construct(RouteOptimizer $optimizer)
    {
        $this->optimizer = $optimizer;
    }

    public function __invoke($data)
    {
        $problem = new RoutingProblem();

        foreach ($data->getTasks() as $task) {
            $problem->addTask($task);
        }

        $firstTask = current($data->getTasks());

        $vehicle = new Vehicle(1);
        $vehicle->setStart($firstTask->getAddress());

        $problem->addVehicle($vehicle);

        $optimizedTasks = $this->optimizer->optimize($problem);

        $data->getItems()->clear();

        foreach ($optimizedTasks as $i => $t) {
            $data->addTask($t, $i);
        }

        return $data;
    }
}
