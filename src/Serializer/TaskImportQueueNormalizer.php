<?php

namespace AppBundle\Serializer;

use AppBundle\Entity\Task\ImportQueue;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Serializer\Normalizer\ContextAwareNormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;

class TaskImportQueueNormalizer implements ContextAwareNormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private const ALREADY_CALLED = 'TASK_IMPORT_QUEUE_NORMALIZER_ALREADY_CALLED';

    public function normalize($object, $format = null, array $context = [])
    {
        if ('completed' === $object->getStatus()) {
            $context['groups'][] = 'task_import_queue_completed';
        }

        if ('failed' === $object->getStatus()) {
            $context['groups'][] = 'task_import_queue_failed';
        }

        $context[self::ALREADY_CALLED] = true;

        $data = $this->normalizer->normalize($object, $format, $context);

        if (isset($data['tasks'])) {
            $data['tasks'] = array_map(function ($task) {

                if (is_array($task) && isset($task['@id'])) {
                    return $task['@id'];
                }

                return $task;

            }, $data['tasks']);
        }

        return $data;
    }

    public function supportsNormalization($data, $format = null, array $context = [])
    {
        // Make sure we're not called twice
        if (isset($context[self::ALREADY_CALLED])) {
            return false;
        }

        return $data instanceof ImportQueue;
    }
}
