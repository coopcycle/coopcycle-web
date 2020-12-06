<?php

namespace AppBundle\Service;

use AppBundle\Serializer\RoutingProblemNormalizer;
use AppBundle\DataType\RoutingProblem;
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
     * @param RoutingProblem $routingProblem a set of jobs and vehicles to optimally dispatch
     */
    public function optimize(RoutingProblem $routingProblem)
    {
        // TODO Catch Exception
        $response = $this->client->request('POST', '', [
            'headers' => ['Content-Type'=> 'application/json'],
            'body' => json_encode($this->normalizer->normalize($routingProblem)),
        ]);

        $tasks = $routingProblem->getTasks();
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
