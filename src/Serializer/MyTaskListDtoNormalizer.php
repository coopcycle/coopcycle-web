<?php

namespace AppBundle\Serializer;

use AppBundle\Api\Dto\MyTaskListDto;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\ContextAwareNormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;

class MyTaskListDtoNormalizer implements ContextAwareNormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private const ALREADY_CALLED = 'MyTaskListDtoNormalizer_ALREADY_CALLED';

    public function normalize($object, $format = null, array $context = array())
    {
        $context[self::ALREADY_CALLED] = true;

        $context[AbstractNormalizer::IGNORED_ATTRIBUTES] = ['date', 'items'];

        $data = $this->normalizer->normalize($object, $format, $context);
        if (!is_array($data)) {
            return $data;
        }

        // override json-ld to match the existing API
        $data['@context'] = '/api/contexts/TaskList';
        $data['@type'] = 'TaskList';
        $data['@id'] = "/api/task_lists/" . $object->id;

        $data['date'] = $object->date->format('Y-m-d');

        $data['items'] = array_map(function ($task) use ($format) {
            return $this->normalizer->normalize(
                $task,
                $format,
                ['groups' => ["task_list", "task_collection", "task", "delivery", "address"]]
            );
        }, $object->items
        );

        return $data;
    }

    public function supportsNormalization($data, ?string $format = null, array $context = [])
    {
        // Make sure we're not called twice
        if (isset($context[self::ALREADY_CALLED])) {
            return false;
        }

        return $data instanceof MyTaskListDto;
    }
}
