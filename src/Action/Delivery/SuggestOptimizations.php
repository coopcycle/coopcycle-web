<?php

namespace AppBundle\Action\Delivery;

use AppBundle\Api\Dto\OptimizationSuggestion;
use AppBundle\Api\Dto\OptimizationSuggestions;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Task;
use AppBundle\Service\RouteOptimizer;
use AppBundle\Service\RoutingInterface;
use AppBundle\Vroom\RoutingProblem;
use AppBundle\Vroom\Vehicle;

class SuggestOptimizations
{
    public function __construct(private RouteOptimizer $optimizer, private RoutingInterface $routing)
    {}

    public function __invoke(Delivery $data): OptimizationSuggestions
    {
        $output = new OptimizationSuggestions();

        // Nothing to optimize
        if (count($data->getTasks()) < 3) {

            return $output;
        }

        $problem = new RoutingProblem();

        $tasks = $data->getTasks();
        $firstTask = current($tasks);

        $registry = [];

        foreach ($tasks as $i => $task) {

            $id = ($i + 1);
            $registry[$id] = $task;

            Task::fixTimeWindow($task);

            $problem->addJob(Task::toVroomJob($task, id: $id));
        }

        $vehicle = new Vehicle(1, 'bike');
        $vehicle->setStart($firstTask->getAddress()->getGeo()->toGeocoderCoordinates());

        $problem->addVehicle($vehicle);

        $solution = $this->optimizer->execute($problem);

        if (isset($solution['unassigned']) && count($solution['unassigned']) > 0) {

            return $output;
        }

        $route = current($solution['routes']);

        $steps = $route['steps'];

        $start = array_shift($steps);

        $reordered = [];
        $optimizedOrder = [];
        foreach ($steps as $step) {
            $reordered[] = $registry[$step['job']];
            $optimizedOrder[] = ($step['job'] - 1);
        }

        $optimized = Delivery::createWithTasks(...$reordered);

        $coords = array_map(fn ($task) => $task->getAddress()->getGeo(), $optimized->getTasks());
        $distance = $this->routing->getDistance(...$coords);

        if ($distance < $data->getDistance()) {

            $suggestion = new OptimizationSuggestion();

            $suggestion->gain = [
                'type' => 'distance',
                'amount' => $data->getDistance() - $distance,
            ];
            $suggestion->order = $optimizedOrder;


            $output->suggestions[] = $suggestion;
        }

        return $output;
    }
}

