<?php

namespace AppBundle\EventSubscriber;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Task;
use AppBundle\Entity\TaskEvent;
use AppBundle\Event\TaskAssignEvent;
use AppBundle\Event\TaskCreateEvent;
use AppBundle\Event\TaskFailedEvent;
use AppBundle\Event\TaskDoneEvent;
use AppBundle\Service\DeliveryManager;
use Doctrine\Bundle\DoctrineBundle\Registry as DoctrineRegistry;
use Predis\Client as Redis;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Serializer\SerializerInterface;

final class TaskSubscriber implements EventSubscriberInterface
{
    private $doctrine;
    private $deliveryManager;
    private $redis;
    private $serializer;

    public function __construct(DoctrineRegistry $doctrine, DeliveryManager $deliveryManager, Redis $redis, SerializerInterface $serializer)
    {
        $this->doctrine = $doctrine;
        $this->deliveryManager = $deliveryManager;
        $this->redis = $redis;
        $this->serializer = $serializer;
    }

    public static function getSubscribedEvents()
    {
        return [
            TaskAssignEvent::NAME => 'onTaskAssign',
            TaskCreateEvent::NAME => 'onTaskCreate',
            TaskFailedEvent::NAME => 'onTaskFailed',
            TaskDoneEvent::NAME   => 'onTaskDone',
        ];
    }

    public function onTaskAssign(TaskAssignEvent $event)
    {
        $task = $event->getTask();
        $user = $event->getUser();

        $taskEvent = new TaskEvent($task, 'ASSIGN');

        $this->doctrine
            ->getManagerForClass(TaskEvent::class)
            ->persist($taskEvent);

        $this->doctrine
            ->getManagerForClass(TaskEvent::class)
            ->flush();

        if (null !== $task->getDelivery()) {
            $this->deliveryManager->dispatch($task->getDelivery(), $user);

            $this->doctrine
                ->getManagerForClass(Delivery::class)
                ->flush();
        }
    }

    public function onTaskCreate(TaskCreateEvent $event)
    {
        $task = $event->getTask();

        $taskEvent = new TaskEvent($task, 'CREATE');

        $this->doctrine
            ->getManagerForClass(TaskEvent::class)
            ->persist($taskEvent);

        $this->doctrine
            ->getManagerForClass(TaskEvent::class)
            ->flush();
    }

    public function onTaskDone(TaskDoneEvent $event)
    {
        $task = $event->getTask();

        $taskEvent = new TaskEvent($task, 'DONE');

        $this->doctrine
            ->getManagerForClass(TaskEvent::class)
            ->persist($taskEvent);

        $this->doctrine
            ->getManagerForClass(TaskEvent::class)
            ->flush();

        $data = $this->serializer->normalize($task, 'jsonld', [
            'resource_class' => Task::class,
            'operation_type' => 'item',
            'item_operation_name' => 'get',
            'groups' => ['task', 'delivery', 'place']
        ]);

        $this->redis->publish('task:done', json_encode($data));
    }

    public function onTaskFailed(TaskFailedEvent $event)
    {
        $task = $event->getTask();

        $taskEvent = new TaskEvent($task, 'FAILED', $event->getReason());

        $this->doctrine
            ->getManagerForClass(TaskEvent::class)
            ->persist($taskEvent);

        $this->doctrine
            ->getManagerForClass(TaskEvent::class)
            ->flush();

        $data = $this->serializer->normalize($task, 'jsonld', [
            'resource_class' => Task::class,
            'operation_type' => 'item',
            'item_operation_name' => 'get',
            'groups' => ['task', 'delivery', 'place']
        ]);

        $this->redis->publish('task:failed', json_encode($data));
    }
}
