<?php

namespace AppBundle\Serializer;

use ApiPlatform\Core\JsonLd\Serializer\ItemNormalizer;
use AppBundle\Entity\Tour;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;


class TourNormalizer implements NormalizerInterface
{
    public function __construct(
        protected ItemNormalizer $normalizer
    )
    {}

    private function flattenItems(array $items)
    {
        return array_values(array_map(function ($item) {
            $task = $item['task'];
            return $task;
        }, $items));
    }

    public function normalize($object, $format = null, array $context = array())
    {
        $data = $this->normalizer->normalize($object, $format, $context);

        if (isset($data['items'])) {
            $data['items'] = $this->flattenItems($data['items']);
        }

        $data['name'] = $object->getName();

        return $data;
    }

    public function supportsNormalization($data, $format = null)
    {
        return $this->normalizer->supportsNormalization($data, $format) && $data instanceof Tour;
    }
}
