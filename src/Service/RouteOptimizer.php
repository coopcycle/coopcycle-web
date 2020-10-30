<?php

namespace AppBundle\Service;

use AppBundle\Serializer\VroomNormalizer;
use GuzzleHttp\Client;

class RouteOptimizer
{
    private $vehicles;
    private $normalizer;

    public function __construct(VroomNormalizer $normalizer, Client $client)
    {
        $this->normalizer = $normalizer;
        $this->client = $client;
    }

    public function optimize(array $tasks)
    {
        // TODO - convert tasks to jobs
        // TODO - convert vehicles
         $response = $this->client->request('POST', '', [
                                                'headers' => ['Content-Type'=> 'application/json'],
                                                'body' => json_encode($this->normalizer->normalize($tasks)),
                                            ]);

         $data = json_decode((string) $response->getBody(), true);
         $firstRoute = $data['routes'][0];
         array_shift($firstRoute['steps']);
         $jobIds = [];
         foreach($firstRoute['steps'] as $step){
            $jobIds[] = $step['id'];
         }
         usort($tasks, function($a, $b) use($jobIds){
            $ka = array_search($a->getId(), $jobIds);
            $kb = array_search($b->getId(), $jobIds);
            return ($ka-$kb);
         });
         return $tasks;
    }

}

?>
