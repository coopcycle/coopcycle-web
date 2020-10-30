<?php

namespace AppBundle\Service;

use AppBundle\Serializer\RoutingProblemNormalizer;
use AppBundle\Entity\RoutingProblem;
use GuzzleHttp\Client;

class RouteOptimizer
{
    private $vehicles;
    private $normalizer;

    public function __construct(RoutingProblemNormalizer $normalizer, Client $client)
    {
        $this->normalizer = $normalizer;
        $this->client = $client;
    }

    public function optimize(RoutingProblem $routingProblem)
    {
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
        foreach($firstRoute['steps'] as $step){
            if(array_key_exists('id', $step)){
                $jobIds[] = $step['id'];
                }
        }

        // sort tasks by ids in steps
        // TODO - sort more efficiently
        usort($tasks, function($a, $b) use($jobIds){
            $ka = array_search($a->getId(), $jobIds);
            $kb = array_search($b->getId(), $jobIds);
            return ($ka-$kb);
        });

        return $tasks;
    }

}

?>
