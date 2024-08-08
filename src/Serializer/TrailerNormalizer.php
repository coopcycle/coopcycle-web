<?php

namespace AppBundle\Serializer;

use ApiPlatform\Core\JsonLd\Serializer\ItemNormalizer;
use AppBundle\Entity\Trailer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;


class TrailerNormalizer implements NormalizerInterface
{
    public function __construct(
        protected ItemNormalizer $normalizer
    )
    {}

    private function flatten(array $items)
    {
        return array_values(array_map(function ($item) {
            return $item['@id'];
        }, $items));
    }

    public function normalize($object, $format = null, array $context = array())
    {

        $data = $this->normalizer->normalize($object, $format, $context);
        if (isset($data['compatibleVehicles'])) {
            $data['compatibleVehicles'] = $this->flatten($data['compatibleVehicles']);
        }
        return $data;
    }

    public function supportsNormalization($data, $format = null)
    {
        return $this->normalizer->supportsNormalization($data, $format) && $data instanceof Trailer;
    }
}
