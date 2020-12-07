<?php

namespace AppBundle\Action\TaskList;

use AppBundle\DataType\RoutingProblem;
use AppBundle\DataType\RoutingProblem\Job;
use AppBundle\DataType\RoutingProblem\Vehicle;
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
        $optimizedTasks = $this->optimizer->optimize($data);

        $data->getItems()->clear();

        foreach ($optimizedTasks as $i => $t) {
            $data->addTask($t, $i);
        }

        return $data;
    }
}
