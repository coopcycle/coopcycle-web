<?php

namespace AppBundle\Serializer;

use Recurr\Rule;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

class RRuleNormalizer implements NormalizerInterface, DenormalizerInterface
{
    private $normalizer;

    public function __construct(ObjectNormalizer $normalizer)
    {
        $this->normalizer = $normalizer;
    }

    public function normalize($object, $format = null, array $context = array())
    {
        return $object->getString();
    }

    public function supportsNormalization($data, $format = null)
    {
        return $this->normalizer->supportsNormalization($data, $format) && $data instanceof Rule;
    }

    public function denormalize($data, $class, $format = null, array $context = array())
    {
        return new Rule($data);
    }

    public function supportsDenormalization($data, $type, $format = null)
    {
        return $type === Rule::class && \is_string($data);
    }
}
