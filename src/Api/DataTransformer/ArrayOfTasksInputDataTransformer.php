<?php

namespace AppBundle\Api\DataTransformer;

use ApiPlatform\Core\DataTransformer\DataTransformerInterface;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Store;
use AppBundle\Entity\Task;
use AppBundle\Api\Dto\ArrayOfTasksInput;

class ArrayOfTasksInputDataTransformer implements DataTransformerInterface
{
    /**
     * {@inheritdoc}
     */
    public function transform($data, string $to, array $context = [])
    {
        return Delivery::createWithTasks(...$data->tasks);
    }

    /**
     * {@inheritdoc}
     */
    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        if ($data instanceof Delivery) {
          return false;
        }

        return Delivery::class === $to
            && null !== ($context['input']['class'] ?? null)
            && $context['input']['class'] === ArrayOfTasksInput::class;
    }
}
