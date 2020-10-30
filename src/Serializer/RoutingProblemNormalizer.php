<?php

namespace AppBundle\Serializer;

use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use AppBundle\Entity\RoutingProblem;

class RoutingProblemNormalizer implements NormalizerInterface
{

    public function normalize($object, $format = null, array $context = array())
    {
        $data = [
            "jobs"=> [],
            "vehicles"=>[],
        ];

        foreach($object->getTasks() as $task){
            $data["jobs"][] = [
                "id"=>$task->getId(),
                "location"=>[
                    $task->getAddress()->getGeo()->getLongitude(),
                    $task->getAddress()->getGeo()->getLatitude()
                ]
            ];
         }

        foreach($object->getVehicles() as $vehicle){
            $data["vehicles"][] = [
                "id"=>$vehicle->getId(),
                "start"=>[
                    $vehicle->getStart()->getGeo()->getLongitude(),
                    $vehicle->getStart()->getGeo()->getLatitude(),
                    ],
                "end"=>[
                    $vehicle->getEnd()->getGeo()->getLongitude(),
                    $vehicle->getEnd()->getGeo()->getLatitude()
                    ]
                ];
        }

        return $data;
    }

    public function supportsNormalization($data, $format = null)
    {
        return $data instanceof RoutingProblem;
    }

}
