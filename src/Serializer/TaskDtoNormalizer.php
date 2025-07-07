<?php

namespace AppBundle\Serializer;

use AppBundle\Api\Dto\TaskDto;
use Symfony\Component\Serializer\Normalizer\ContextAwareNormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;

class TaskDtoNormalizer implements ContextAwareNormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private const ALREADY_CALLED = 'TaskDtoNormalizer_ALREADY_CALLED';

    /**
     * @param TaskDto $object
     */
    public function normalize($object, $format = null, array $context = array())
    {
        $context[self::ALREADY_CALLED] = true;

        $data = $this->normalizer->normalize($object, $format, $context);
        if (!is_array($data)) {
            return $data;
        }

        // override json-ld to match the existing API
        if (isset($data['@context'])) {
            $data['@context'] = '/api/contexts/Task';
        }
        $data['@type'] = 'Task';
        $data['@id'] = "/api/tasks/" . $object->id;

        // Make sure "comments" is a string
        if (is_null($data['comments'])) {
            $data['comments'] = '';
        }

        return $data;
    }

    public function supportsNormalization($data, ?string $format = null, array $context = []): bool
    {
        // Make sure we're not called twice
        if (isset($context[self::ALREADY_CALLED])) {
            return false;
        }

        return $data instanceof TaskDto;
    }
}
