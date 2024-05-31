<?php

namespace AppBundle\EventListener\Edifact;

use AppBundle\Entity\Edifact\EDIFACTMessage;
use AppBundle\Entity\Task;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostUpdateEventArgs;

class TaskChangedNotifier {

    public function __construct(
        private EntityManagerInterface $em
    ) { }

    public function __invoke(Task $task, PostUpdateEventArgs $event): void
    {
        if ($this->shouldEventBeIgnored($task)) {
            return;
        }
        $em = $event->getObjectManager();
        $uow = $em->getUnitOfWork();
        $changeset = $uow->getEntityChangeSet($task);
        $this->handleChangeset($task, $changeset);
    }

    private function handleChangeset(Task $task, array $changeset): void
    {
        if (!in_array('status', array_keys($changeset))) {
            return;
        }

        match ([$task->getType(), $changeset['status'][1]]) {
            [Task::TYPE_PICKUP, Task::STATUS_DOING] => $this->scheduleEdifactSubMessage($task, 'AAR|CFM'),
            [Task::TYPE_PICKUP, Task::STATUS_DONE]  => $this->scheduleEdifactSubMessage($task, 'MLV|CFM'),
            [Task::TYPE_DROPOFF, Task::STATUS_DONE] => $this->scheduleEdifactSubMessage($task, 'LIV|CFM'),
        };
   }

    private function scheduleEdifactSubMessage(
        Task $task,
        string $subMessageType
    ): void
    {
        $importMessage = $task->getImportMessage();
        $ediMessage = new EDIFACTMessage();
        $ediMessage->setMessageType(EDIFACTMessage::MESSAGE_TYPE_REPORT);
        $ediMessage->setTransporter($importMessage->getTransporter());
        $ediMessage->setDirection(EDIFACTMessage::DIRECTION_OUTBOUND);
        $ediMessage->setReference($importMessage->getReference());
        $ediMessage->setSubMessageType($subMessageType);

        $task->addEdifactMessage($ediMessage);
        $this->em->persist($ediMessage);
        $this->em->persist($task);
        $this->em->flush();
    }

    private function shouldEventBeIgnored(Task $task): bool
    {
        return empty($task->getEdifactMessages());
    }

}
