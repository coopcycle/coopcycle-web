<?php

declare(strict_types=1);

namespace AppBundle\Entity\Listener;

use AppBundle\Entity\Task;
use AppBundle\Message\RdcDropoffStatusUpdateMessage;
use AppBundle\Message\RdcPickupStatusUpdateMessage;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

class TaskRdcStatusListener
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly EntityManagerInterface $entityManager,
        private readonly bool $rdcEnabled = false,
    ) {}

    public function postUpdate(Task $task, PostUpdateEventArgs $event): void
    {
        if (!$this->rdcEnabled) {
            return;
        }

        $changeSet = $this->entityManager->getUnitOfWork()->getEntityChangeSet($task);

        if (!isset($changeSet['status'])) {
            return;
        }

        $delivery = $task->getDelivery();
        if ($delivery === null) {
            return;
        }

        $store = $delivery->getStore();
        if ($store === null || $store->getRdcConnectionId() === null) {
            return;
        }

        $actionTime = new \DateTimeImmutable();

        if ($task->isPickup()) {
            $this->messageBus->dispatch(
                new RdcPickupStatusUpdateMessage(
                    taskId: $task->getId(),
                    coopcycleStatus: $changeSet['status'][1],
                    actionTime: $actionTime,
                ),
                [new DelayStamp(45000)]
            );
        } else {
            $this->messageBus->dispatch(
                new RdcDropoffStatusUpdateMessage(
                    taskId: $task->getId(),
                    coopcycleStatus: $changeSet['status'][1],
                    actionTime: $actionTime,
                ),
                [new DelayStamp(45000)]
            );
        }
    }
}
