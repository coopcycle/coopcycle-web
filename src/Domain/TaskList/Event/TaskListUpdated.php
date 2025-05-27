<?php

namespace AppBundle\Domain\TaskList\Event;

use AppBundle\Api\Dto\MyTaskListDto;
use AppBundle\Domain\TaskList\Event as BaseEvent;
use AppBundle\Domain\SerializableEventInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class TaskListUpdated extends BaseEvent implements SerializableEventInterface
{

    public function __construct(
        private readonly UserInterface $courier,
        private readonly MyTaskListDto $collection
    )
    {
    }

    public function getCourier(): UserInterface
    {
        return $this->courier;
    }

    public function getTaskList(): MyTaskListDto
    {
        return $this->collection;
    }

    public function normalize(NormalizerInterface $serializer)
    {
        $normalized = $serializer->normalize($this->collection, 'jsonld', [
            'resource_class' => MyTaskListDto::class,
            'groups' => ['task_list', "task_collection", "task", "delivery", "address"]
        ]);

        return [
            'task_list' => $normalized
        ];
    }

    public static function messageName(): string
    {
        return 'task_list:updated';
    }
}
