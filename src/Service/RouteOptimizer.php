<?php

namespace AppBundle\Service;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Task;
use AppBundle\Vroom\RoutingProblem;
use AppBundle\Vroom\RoutingProblemNormalizer;
use AppBundle\Vroom\Job;
use AppBundle\Vroom\Shipment;
use AppBundle\Vroom\Vehicle;
use AppBundle\Entity\TaskCollection;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
* a class to connect a given routing problem with the vroom api to return optimal results
*/

class RouteOptimizer
{
    private $vroomClient;

    public function __construct(HttpClientInterface $vroomClient)
    {
        $this->vroomClient = $vroomClient;
    }

    /**
     * return a list of tasks sorted into an optimal route as obtained from the vroom api
     *
     * @param TaskCollection $taskCollection
     * @return array
     */
    public function optimize(TaskCollection $taskCollection)
    {
        $routingProblem = $this->createRoutingProblem($taskCollection);

        $normalizer = new RoutingProblemNormalizer();

        // TODO Catch Exception
        $response = $this->vroomClient->request('POST', '', [
            'headers' => ['Content-Type'=> 'application/json'],
            'body' => json_encode($normalizer->normalize($routingProblem)),
        ]);

        $tasks = $taskCollection->getTasks();
        $data = json_decode((string) $response->getContent(), true);

        $firstRoute = $data['routes'][0];
        array_shift($firstRoute['steps']);

        $jobIds = [];
        // extract task ids from steps
        foreach ($firstRoute['steps'] as $step) {
            if (array_key_exists('id', $step)) {
                $jobIds[] = $step['id'];
            }
        }

        // sort tasks by ids in steps
        usort($tasks, function($a, $b) use($jobIds){
            $ka = array_search($a->getId(), $jobIds);
            $kb = array_search($b->getId(), $jobIds);
            return ($ka - $kb);
        });

        return $tasks;
    }

    /**
     * @param TaskCollection $taskCollection
     * @return RoutingProblem
     */
    public function createRoutingProblem(TaskCollection $taskCollection)
    {
        $routingProblem = new RoutingProblem();

        $deliveries = [];
        foreach ($taskCollection->getTasks() as $task) {
            if (null !== $task->getDelivery()) {
                if (!in_array($task->getDelivery(), $deliveries, true)) {
                    $deliveries[] = $task->getDelivery();
                }
            } else {
                $routingProblem->addJob(Task::toVroomJob($task));
            }
        }

        foreach ($deliveries as $delivery) {
            $routingProblem->addShipment(Delivery::toVroomShipment($delivery));
        }

        $firstTask = current($taskCollection->getTasks());

        $vehicle = new Vehicle(1, 'bike');
        $vehicle->setStart($firstTask->getAddress()->getGeo()->toGeocoderCoordinates());

        $routingProblem->addVehicle($vehicle);

        return $routingProblem;
    }
}
