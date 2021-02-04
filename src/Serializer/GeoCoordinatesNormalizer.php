<?php

namespace AppBundle\Serializer;

use ApiPlatform\Core\JsonLd\Serializer\ObjectNormalizer;
use AppBundle\Entity\Base\GeoCoordinates;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class GeoCoordinatesNormalizer implements NormalizerInterface
{
    private $normalizer;

    public function __construct(ObjectNormalizer $normalizer)
    {
        $this->normalizer = $normalizer;
    }

    public function normalize($object, $format = null, array $context = array())
    {
        $data = $this->normalizer->normalize($object, $format, $context);

        // Avoid returning a new "@id" property each time
        // @see https://github.com/coopcycle/coopcycle-web/issues/2092
        unset($data['@id']);

        return $data;
    }

    public function supportsNormalization($data, $format = null)
    {
        return $this->normalizer->supportsNormalization($data, $format) && $data instanceof GeoCoordinates;
    }
}
