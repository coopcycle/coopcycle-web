<?php

namespace AppBundle\EventListener\Edifact;

use AppBundle\Entity\Edifact\EDIFACTMessage;
use AppBundle\Entity\Task;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Psr\Log\LoggerInterface;

class TaskChangedNotifier {

    public function __construct(
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
        private TransporterPodNotifier $podNotifier,
    ) { }

    public function __invoke(Task $task, PostUpdateEventArgs $event): void
    {
        $em = $event->getObjectManager();
        $uow = $em->getUnitOfWork();
        $changeset = $uow->getEntityChangeSet($task);

        // Everything below only acts on a status transition, and the changeset
        // is already in memory -- so test it before shouldEventBeIgnored(),
        // which initialises the task's EDIFACT messages. That collection is
        // lazy, so asking it first meant one query for every updated task,
        // always returning nothing for a coop with no transporter integration.
        if (!isset($changeset['status'])) {
            return;
        }

        if ($this->shouldEventBeIgnored($task)) {
            return;
        }

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
            default => $this->logger->warning(sprintf(
                'Unexpected task type "%s" and status "%s"',
                $task->getType(), $changeset['status'][1])
            )
        };

        // The proof may already be there: the images are uploaded either side
        // of this transition. TransporterPodNotifier is a no-op if not.
        $this->podNotifier->notify($task);
   }

    private function scheduleEdifactSubMessage(
        Task $task,
        string $subMessageType
    ): void
    {
        $importMessage = $task->getImportMessage();
        if (is_null($importMessage)) {
            $this->logger->warning(sprintf(
                'Task "%s" has no import message',
                $task->getId()
            ));
            return;
        }
        $ediMessage = EDIFACTMessage::createReport($importMessage, $subMessageType);

        $task->addEdifactMessage($ediMessage);
        $this->em->persist($ediMessage);
        $this->em->persist($task);
        $this->em->flush();
    }

    private function shouldEventBeIgnored(Task $task): bool
    {
        return $task->getEdifactMessages()->count() === 0;
    }

}
