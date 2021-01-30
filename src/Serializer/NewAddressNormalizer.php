<?php

namespace AppBundle\Serializer;

use AppBundle\Entity\Address;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

class NewAddressNormalizer implements NormalizerInterface
{
    private $normalizer;

    public function __construct(ObjectNormalizer $normalizer)
    {
        $this->normalizer = $normalizer;
    }

    public function normalize($object, $format = null, array $context = array())
    {
        $context = array_merge($context, ['iri' => false]);

        $data = $this->normalizer->normalize($object, $format, $context);

        unset($data['geo']['@context']);
        unset($data['geo']['@type']);
        unset($data['geo']['@id']);

        return $data;
    }

    public function supportsNormalization($data, $format = null)
    {
        return $this->normalizer->supportsNormalization($data, $format) &&
            $data instanceof Address && null === $data->getId();
    }
}
