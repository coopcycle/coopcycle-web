<?php

namespace AppBundle\Api\DataTransformer;

use ApiPlatform\Core\DataTransformer\DataTransformerInterface;
use AppBundle\Entity\Task;
use ApiPlatform\Core\Serializer\AbstractItemNormalizer;

/**
 * This is a special use case for Tricargo customers using BioDeliver software.
 */
class BioDeliverInputDataTransformer implements DataTransformerInterface
{
    /**
     * {@inheritdoc}
     */
    public function transform($data, string $to, array $context = [])
    {
        $task = $context[AbstractItemNormalizer::OBJECT_TO_POPULATE];

        if (is_array($data->address) && isset($data->address['name']) && !empty($data->address['name'])) {
            $task->getAddress()->setName($data->address['name']);
        }

        if (!empty($data->comments)) {
            $task->setComments($data->comments);
        }

        return $task;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        if ($data instanceof Task) {
          return false;
        }

        return Task::class === $to && null !== ($context['input']['class'] ?? null);
    }
}

