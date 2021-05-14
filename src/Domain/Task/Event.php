<?php

namespace AppBundle\Domain\Task;

use AppBundle\Domain\Event as BaseEvent;
use AppBundle\Domain\HumanReadableEventInterface;
use AppBundle\Domain\SerializableEventInterface;
use AppBundle\Entity\Task;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

abstract class Event extends BaseEvent implements SerializableEventInterface, HumanReadableEventInterface
{
    protected $task;

    public function __construct(Task $task)
    {
        $this->task = $task;
    }

    public function getTask(): Task
    {
        return $this->task;
    }

    public function normalize(NormalizerInterface $serializer)
    {
        $normalized = $serializer->normalize($this->getTask(), 'jsonld', [
            'resource_class' => Task::class,
            'operation_type' => 'item',
            'item_operation_name' => 'get',
            'groups' => ['task', 'delivery', 'address']
        ]);

        return [
            'task' => $normalized
        ];
    }

    public function forHumans(TranslatorInterface $translator, UserInterface $user = null)
    {
        $params = [
            '%aggregate_id%' => $this->getTask()->getId(),
            '%owner%' => $user ? $user->getUsername() : '?',
        ];

        $key = 'activity.' . str_replace(':', '.', $this::messageName());

        return trim($translator->trans($key, $params));
    }
}
