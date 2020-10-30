<?php

namespace AppBundle\Serializer;

use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class VroomNormalizer implements NormalizerInterface
{

    private $normalizer;

    public function normalize($object, $format = null, array $context = array())
    {
        $data = [];
        foreach($object as $task){
            $data[] = ["id"=>$task->getId(), "location"=>[$task->getAddress()->getGeo()->getLongitude(), $task->getAddress()->getGeo()->getLatitude()]];
        }
        return $data;
    }

    public function supportsNormalization($data, $format = null)
    {
        return $this->normalizer->supportsNormalization($data, $format) && $data instanceof Task;
    }

}
