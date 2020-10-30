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
         $response = $this->client->request('POST', '/', [
                                                'body' => json_encode($this->normalizer->normalize($tasks)),
                                            ]);

         $data = json_decode((string) $response->getBody(), true);
         var_dump($data);
    }

    private function sendVroomRequest($action)
    {

    }
}

?>
