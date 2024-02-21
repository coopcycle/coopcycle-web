<?php

namespace AppBundle\EventListener\Edifact;

use AppBundle\Entity\Edifact\EDIFACTMessage;
use AppBundle\Entity\Task;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use AppBundle\Entity\Store;

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
        // Check if status is done
        if (
            in_array('status', array_keys($changeset)) &&
            $changeset['status'][1] === Task::STATUS_DONE
        ) {
            $importMessage = $task->getImportMessage();
            $ediMessage = new EDIFACTMessage();
            $ediMessage->setMessageType(EDIFACTMessage::MESSAGE_TYPE_REPORT);
            $ediMessage->setTransporter($importMessage->getTransporter());
            $ediMessage->setDirection(EDIFACTMessage::DIRECTION_OUTBOUND);
            $ediMessage->setReference($importMessage->getReference());
            $ediMessage->setSubMessageType('LIV|CFM');

            $task->addEdifactMessage($ediMessage);
            $this->em->persist($ediMessage);
            $this->em->persist($task);
            $this->em->flush();

        }
   }

    private function shouldEventBeIgnored(Task $task): bool
    {
        if ($task->getType() !== Task::TYPE_DROPOFF) {
            return true;
        }
        $org = $task->getOrganization();
        if (is_null($org)) {
            return true;
        }

        /** @var Store $store */
        $store = $this->em->getRepository(Store::class)->findOneBy([
            'organization' => $org
        ]);

        return !$store->isDBSchenkerEnabled();
    }

}
