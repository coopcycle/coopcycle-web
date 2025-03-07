<?php

namespace AppBundle\Entity\Listener;

use AppBundle\Entity\ClosingRule;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;

#[AsEntityListener(event: Events::postPersist, entity: ClosingRule::class)]
#[AsEntityListener(event: Events::preRemove, entity: ClosingRule::class)]
#[AsEntityListener(event: Events::postRemove, entity: ClosingRule::class)]
class ClosingRuleListener
{
    private ?int $entityToBeRemoved = null;

    public function __construct(
        private readonly LoggerInterface $domainEventLogger)
    {
    }

    public function postPersist(ClosingRule $entity, PostPersistEventArgs $args): void
    {
        $this->domainEventLogger->info('ClosingRule created', [
            'closingRule' => $entity->getId()
        ]);
    }

    public function preRemove(ClosingRule $entity, PreRemoveEventArgs $args): void
    {
        $this->entityToBeRemoved = $entity->getId();;
    }

    public function postRemove(ClosingRule $entity, PostRemoveEventArgs $args): void
    {
        $this->domainEventLogger->info('ClosingRule removed', [
            'closingRule' => $this->entityToBeRemoved
        ]);
    }
}
