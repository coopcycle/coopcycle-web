<?php

namespace AppBundle\MessageHandler\Task;

use ApiPlatform\Core\Api\IriConverterInterface;
use AppBundle\Domain\Task\Event;
use AppBundle\Domain\Task\Event\TaskAssigned;
use AppBundle\Domain\Task\Event\TaskDone;
use AppBundle\Domain\Task\Event\TaskFailed;
use AppBundle\Domain\Task\Event\TaskIncidentReported;
use AppBundle\Domain\Task\Event\TaskStarted;
use AppBundle\Entity\Woopit\Delivery as WoopitDelivery;
use AppBundle\Message\Webhook;
use AppBundle\Message\WoopitWebhook;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler()]
class TriggerWebhook
{
    public function __construct(
        private MessageBusInterface $messageBus,
        private IriConverterInterface $iriConverter,
        private EntityManagerInterface $entityManager)
    {}

    public function __invoke(TaskAssigned|TaskStarted|TaskFailed|TaskIncidentReported|TaskDone $event)
    {
        $task = $event->getTask();

        if (null === $task->getDelivery()) {
            return;
        }

        $this->messageBus->dispatch(
            new Webhook(
                $this->iriConverter->getIriFromItem($task->getDelivery()),
                $this->getEventName($event)
            )
        );

        $woopitDelivery = $this->entityManager
            ->getRepository(WoopitDelivery::class)
            ->findOneBy(['delivery' => $task->getDelivery()]);

        if ($woopitDelivery) {
            $this->messageBus->dispatch(
                new WoopitWebhook(
                    $this->iriConverter->getIriFromItem($task->getDelivery()),
                    $this->getWoopitEventName($event)
                )
            );
        }
    }

    private function getEventName(Event $event)
    {
        $task = $event->getTask();

        if ($event instanceof Event\TaskAssigned && $task->isDropoff()) {
            return 'delivery.assigned';
        }

        if ($event instanceof Event\TaskStarted) {
            return $task->isPickup() ? 'delivery.started' : 'delivery.in_transit';
        }

        if ($event instanceof Event\TaskFailed) {
            return 'delivery.failed';
        }

        if ($event instanceof Event\TaskDone) {
            return $task->isPickup() ? 'delivery.picked' : 'delivery.completed';
        }

        return '';
    }

    private function getWoopitEventName(Event $event)
    {
        $task = $event->getTask();

        if ($event instanceof Event\TaskAssigned && $task->isDropoff()) {
            return 'delivery.assigned';
        }

        if ($event instanceof Event\TaskStarted) {
            return $task->isPickup() ? 'delivery.started' : 'delivery.in_progress';
        }

        if ($event instanceof Event\TaskDone) {
            return $task->isPickup() ? 'delivery.picked' : 'delivery.completed';
        }

        if ($event instanceof Event\TaskFailed) {
            return $task->isPickup() ? 'delivery.pickup_failed' : 'delivery.failed';
        }

        return '';
    }
}
