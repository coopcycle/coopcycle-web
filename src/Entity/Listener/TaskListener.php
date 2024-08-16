<?php

namespace AppBundle\Entity\Listener;

use ApiPlatform\Core\Api\IriConverterInterface;
use AppBundle\Entity\Task;
use AppBundle\Entity\Woopit\Delivery as WoopitDelivery;
use AppBundle\Message\WoopitDocumentWebhook;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Symfony\Component\Messenger\MessageBusInterface;

class TaskListener
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly IriConverterInterface $iriConverter,
        private readonly EntityManagerInterface $entityManager)
    {
    }

    public function prePersist(Task $task, LifecycleEventArgs $args)
    {
        Task::fixTimeWindow($task);
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
