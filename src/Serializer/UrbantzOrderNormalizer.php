<?php

namespace AppBundle\Serializer;

use AppBundle\Api\Dto\UrbantzOrderInput;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

class UrbantzOrderNormalizer implements NormalizerInterface, DenormalizerInterface
{
    public function normalize($object, $format = null, array $context = array())
    {
        return [];
    }

    public function supportsNormalization($data, $format = null)
    {
        return false;
    }

    public function denormalize($data, $class, $format = null, array $context = array())
    {
        $object = new UrbantzOrderInput();
        $object->tasks = $data;

        return $object;
    }

    public function supportsDenormalization($data, $type, $format = null)
    {
        return $type === UrbantzOrderInput::class;
    }
}
