<?php

namespace AppBundle\Service;

use AppBundle\Serializer\RoutingProblemNormalizer;
use AppBundle\DataType\RoutingProblem;
use AppBundle\DataType\RoutingProblem\Job;
use AppBundle\DataType\RoutingProblem\Vehicle;
use AppBundle\Entity\TaskCollection;
use GuzzleHttp\Client;

/**
* a class to connect a given routing problem with the vroom api to return optimal results
*/

class RouteOptimizer
{
    private $normalizer;

    public function __construct(RoutingProblemNormalizer $normalizer, Client $client)
    {
        $this->normalizer = $normalizer;
        $this->client = $client;
    }

    /**
     * return a list of tasks sorted into an optimal route as obtained from the vroom api
     *
     * @param TaskCollection $taskCollection
     * @return array
     */
    public function optimize(TaskCollection $taskCollection)
    {
        $routingProblem = new RoutingProblem();

        foreach ($taskCollection->getTasks() as $task) {
            $routingProblem->addJob(Job::fromTask($task));
        }

        $firstTask = current($taskCollection->getTasks());

        $vehicle = new Vehicle(1);
        $vehicle->setStart($firstTask->getAddress());

        $routingProblem->addVehicle($vehicle);

        // TODO Catch Exception
        $response = $this->client->request('POST', '', [
            'headers' => ['Content-Type'=> 'application/json'],
            'body' => json_encode($this->normalizer->normalize($routingProblem)),
        ]);

        $tasks = $taskCollection->getTasks();
        $data = json_decode((string) $response->getBody(), true);

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
}
