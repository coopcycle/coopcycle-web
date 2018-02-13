<?php

namespace AppBundle\EventSubscriber;

use AppBundle\Entity\ApiUser;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Task;
use AppBundle\Entity\TaskEvent;
use AppBundle\Event\TaskAssignEvent;
use AppBundle\Event\TaskCreateEvent;
use AppBundle\Event\TaskDoneEvent;
use AppBundle\Event\TaskFailedEvent;
use AppBundle\Event\TaskUnassignEvent;
use AppBundle\Service\DeliveryManager;
use Doctrine\Bundle\DoctrineBundle\Registry as DoctrineRegistry;
use FOS\UserBundle\Model\UserInterface;
use Predis\Client as Redis;
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
            TaskAssignEvent::NAME   => 'onTaskAssign',
            TaskCreateEvent::NAME   => 'onTaskCreate',
            TaskFailedEvent::NAME   => 'onTaskFailed',
            TaskDoneEvent::NAME     => 'onTaskDone',
            TaskUnassignEvent::NAME => 'onTaskUnassign',
        ];
    }

    private function addEvent(Task $task, $name, $notes = null)
    {
        $event = new TaskEvent($task, $name, $notes);

        $this->doctrine
            ->getManagerForClass(TaskEvent::class)
            ->persist($event);

        $this->doctrine
            ->getManagerForClass(TaskEvent::class)
            ->flush();
    }

    private function publishTaskEventToRedis(Task $task, UserInterface $user, $channelName) {
        $serializedUser = $this->serializer->normalize($user, 'jsonld', [
            'resource_class' => ApiUser::class,
            'operation_type' => 'item',
            'item_operation_name' => 'get'
        ]);

        $serializedTask = $this->serializer->normalize($task, 'jsonld', [
            'resource_class' => Task::class,
            'operation_type' => 'item',
            'item_operation_name' => 'get',
            'groups' => ['task', 'delivery', 'place']
        ]);

        $this->redis->publish(
            $channelName,
            json_encode(['task' => $serializedTask, 'user' => $serializedUser]));
    }

    public function onTaskAssign(TaskAssignEvent $event)
    {
        $task = $event->getTask();
        $user = $event->getUser();

        $this->addEvent($task, 'ASSIGN');

        if (null !== $task->getDelivery()) {
            $this->deliveryManager->dispatch($task->getDelivery(), $user);

            $this->doctrine
                ->getManagerForClass(Delivery::class)
                ->flush();
        }

        $this->publishTaskEventToRedis($task, $user, 'task:assign');
    }

    public function onTaskUnassign(TaskUnassignEvent $event)
    {
        $task = $event->getTask();
        $user = $event->getUser();

        $this->addEvent($task, 'UNASSIGN');

        if (null !== $task->getDelivery()) {
            $task->getDelivery()->setCourier(null);
            $task->getDelivery()->setStatus(Delivery::STATUS_WAITING);

            $this->doctrine
                ->getManagerForClass(Delivery::class)
                ->flush();
        }

        $this->publishTaskEventToRedis($task, $user, 'task:unassign');
    }

    public function onTaskCreate(TaskCreateEvent $event)
    {
        $task = $event->getTask();

        $this->addEvent($task, 'CREATE');
    }

    public function onTaskDone(TaskDoneEvent $event)
    {
        $task = $event->getTask();
        $user = $event->getUser();

        $this->addEvent($task, 'DONE');

        if (null !== $task->getDelivery()) {
            if ($task->isPickup()) {
                $task->getDelivery()->setStatus(Delivery::STATUS_PICKED);
            }
            if ($task->isDropoff()) {
                $task->getDelivery()->setStatus(Delivery::STATUS_DELIVERED);
            }
            $this->doctrine
                ->getManagerForClass(Delivery::class)
                ->flush();
        }

        $this->publishTaskEventToRedis($task, $user, 'task:done');
    }

    public function onTaskFailed(TaskFailedEvent $event)
    {
        $task = $event->getTask();
        $user = $event->getUser();

        $this->addEvent($task, 'FAILED', $event->getReason());


        $this->publishTaskEventToRedis($task, $user, 'task:failed');
    }
}
