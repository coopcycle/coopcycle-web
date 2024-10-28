<?php

namespace AppBundle\Entity\Listener;

use AppBundle\Entity\LocalBusiness;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;

#[AsEntityListener(event: Events::postPersist, entity: LocalBusiness::class)]
#[AsEntityListener(event: Events::preUpdate, entity: LocalBusiness::class)]
#[AsEntityListener(event: Events::postUpdate, entity: LocalBusiness::class)]
class LocalBusinessListener
{
    private array $entityChangeSet = [];

    public function __construct(
        private readonly LoggerInterface $domainEventLogger)
    {
    }

    public function postPersist(LocalBusiness $entity, PostPersistEventArgs $args): void
    {
        $this->domainEventLogger->info('Restaurant created', [
            'restaurant' => $entity->getId()
        ]);
        $this->logIsEnabled($entity);
    }

    public function preUpdate(LocalBusiness $entity, PreUpdateEventArgs $args): void
    {
        $this->entityChangeSet = $args->getEntityChangeSet();
    }

    public function postUpdate(LocalBusiness $entity, PostUpdateEventArgs $args): void
    {
        if (isset($this->entityChangeSet['enabled'])) {
            $this->logIsEnabled($entity);
        }

        if (isset($this->entityChangeSet['deletedAt'])) {
            $this->domainEventLogger->info('Restaurant deleted', [
                'restaurant' => $entity->getId()
            ]);
        }
    }

    private function logIsEnabled(LocalBusiness $entity): void
    {
        if ($entity->isEnabled()) {
            $this->domainEventLogger->info('Restaurant enabled', [
                'restaurant' => $entity->getId()
            ]);
        } else {
            $this->domainEventLogger->info('Restaurant disabled', [
                'restaurant' => $entity->getId()
            ]);
        }
    }
}
