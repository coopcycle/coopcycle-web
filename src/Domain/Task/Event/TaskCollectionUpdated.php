<?php

namespace AppBundle\Domain\Task\Event;

use AppBundle\Domain\Event as BaseEvent;
use AppBundle\Domain\SerializableEventInterface;
use AppBundle\Entity\Task\CollectionInterface as TaskCollectionInterface;
use AppBundle\Entity\TaskList;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class TaskCollectionUpdated extends BaseEvent implements SerializableEventInterface
{
    protected $collection;

    public function __construct(TaskCollectionInterface $collection)
    {
        $this->collection = $collection;
    }

    public function normalize(NormalizerInterface $serializer)
    {
        $normalized = $serializer->normalize($this->collection, 'jsonld', [
            'resource_class' => TaskList::class,
            'operation_type' => 'item',
            'item_operation_name' => 'get',
            'groups' => ['task_collection']
        ]);

        return [
            'task_collection' => $normalized
        ];
    }

    public static function messageName(): string
    {
        return 'task_collection:updated';
    }
}
