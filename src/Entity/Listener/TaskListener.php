<?php

namespace AppBundle\Entity\Listener;

use ApiPlatform\Core\Api\IriConverterInterface;
use AppBundle\Entity\Task;
use AppBundle\Entity\Woopit\Delivery as WoopitDelivery;
use AppBundle\Message\WoopitDocumentWebhook;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class TaskListener
{
    private $messageBus;

    public function __construct(
        MessageBusInterface $messageBus,
        IriConverterInterface $iriConverter,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger)
    {
        $this->messageBus = $messageBus;
        $this->iriConverter = $iriConverter;
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }

    public function prePersist(Task $task, LifecycleEventArgs $args)
    {
        if (null === $task->getDoneAfter()) {
            $doneAfter = clone $task->getDoneBefore();
            $doneAfter->modify('-15 minutes');
            $task->setDoneAfter($doneAfter);
        }
    }

    public function preUpdate(Task $task, PreUpdateEventArgs $event)
    {
        if ($event->hasChangedField('images')) {
            $woopitDelivery = $this->entityManager
                ->getRepository(WoopitDelivery::class)
                ->findOneBy(['delivery' => $task->getDelivery()]);

            if ($woopitDelivery) {
                foreach($task->getImages() as $taskImage) {
                    $this->messageBus->dispatch(
                        new WoopitDocumentWebhook(
                            $this->iriConverter->getIriFromItem($taskImage),
                            'EVIDENCE'
                        )
                    );
                }
            }
        }
    }

}
