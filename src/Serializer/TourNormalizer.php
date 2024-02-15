<?php

namespace AppBundle\Serializer;

use ApiPlatform\Core\Api\IriConverterInterface;
use ApiPlatform\Core\JsonLd\Serializer\ItemNormalizer;
use AppBundle\Entity\Tour;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class TourNormalizer implements NormalizerInterface
{
    private $normalizer;

    public function __construct(ItemNormalizer $normalizer)
    {
        $this->normalizer = $normalizer;
    }

    private function flattenItems(array $items)
    {
        return array_values(array_map(function ($item) {

            if (!is_array($item['task'])) {

                return $item;
            }

            $position = $item['position'];
            $task = $item['task'];

            return array_merge($task, ['position' => $position]);
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
