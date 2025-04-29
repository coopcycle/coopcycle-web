<?php

namespace AppBundle\Domain\TaskList;

use AppBundle\Api\Dto\MyTaskListDto;
use AppBundle\Domain\Event as BaseEvent;
use AppBundle\Domain\SerializableEventInterface;
use AppBundle\Entity\TaskList;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

abstract class Event extends BaseEvent implements SerializableEventInterface
{
    public function __construct(private readonly TaskList $collection)
    {}

    public function getTaskList(): TaskList|MyTaskListDto
    {
        return $this->collection;
    }

    public function normalize(NormalizerInterface $serializer)
    {
        $normalized = $serializer->normalize($this->collection, 'jsonld', [
            'resource_class' => TaskList::class,
            'operation_type' => 'item',
            'item_operation_name' => 'get',
            'groups' => ['task_list']
        ]);

        return [
            'task_list' => $normalized
        ];
    }
}
