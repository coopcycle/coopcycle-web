<?php

namespace AppBundle\Domain\Task\Event;

use AppBundle\Domain\Event as BaseEvent;
use AppBundle\Domain\SerializableEventInterface;
use AppBundle\Entity\Task\CollectionInterface as TaskCollectionInterface;
use AppBundle\Entity\TaskList;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class TaskCollectionsUpdated extends BaseEvent implements SerializableEventInterface
{
    protected $collections = [];

    /**
     * @param TaskCollectionInterface[] $collections
     */
    public function __construct(array $collections = [])
    {
        $this->collections = $collections;
    }

    public function normalize(NormalizerInterface $serializer)
    {
        $normalized = [];
        foreach ($this->collections as $collection) {
            $normalized[] = $serializer->normalize($collection, 'jsonld', [
                'resource_class' => TaskList::class,
                'operation_type' => 'item',
                'item_operation_name' => 'get',
                'groups' => ['task_collections']
            ]);
        }

        return [
            'task_collections' => $normalized
        ];
    }

    public static function messageName(): string
    {
        return 'task_collections:updated';
    }
}
