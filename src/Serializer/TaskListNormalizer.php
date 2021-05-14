<?php

namespace AppBundle\Serializer;

use ApiPlatform\Core\Api\IriConverterInterface;
use ApiPlatform\Core\JsonLd\Serializer\ItemNormalizer;
use AppBundle\Entity\TaskList;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class TaskListNormalizer implements NormalizerInterface, DenormalizerInterface
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

        // Legacy
        if (isset($context['item_operation_name']) && $context['item_operation_name'] === 'my_tasks') {
            $data['hydra:member'] = $data['items'];
            $data['hydra:totalItems'] = count($data['items']);
        }

        return $data;
    }

    public function supportsNormalization($data, $format = null)
    {
        return $this->normalizer->supportsNormalization($data, $format) && $data instanceof TaskList;
    }

    public function denormalize($data, $class, $format = null, array $context = array())
    {
        return $this->normalizer->denormalize($data, $class, $format, $context);
    }

    public function supportsDenormalization($data, $type, $format = null)
    {
        return $this->normalizer->supportsDenormalization($data, $type, $format) && $type === TaskList::class;
    }
}
