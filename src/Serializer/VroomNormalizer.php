<?php

namespace AppBundle\Serializer;

use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class VroomNormalizer implements NormalizerInterface
{

    private $normalizer;

    public function normalize($object, $format = null, array $context = array())
    {
        $data = ["jobs"=> [],
        "vehicles"=>[
            ["id"=>1,
            "start"=>[$object[0]->getAddress()->getGeo()->getLongitude(), $object[0]->getAddress()->getGeo()->getLatitude()],
            ]]
        ];
        foreach($object as $task){
            $data["jobs"][] = ["id"=>$task->getId(), "location"=>[$task->getAddress()->getGeo()->getLongitude(), $task->getAddress()->getGeo()->getLatitude()]];

        }
        return $data;
    }

    public function supportsNormalization($data, $format = null)
    {
        return $this->normalizer->supportsNormalization($data, $format) && $data instanceof Task;
    }

}
