<?php

namespace AppBundle\Entity\Listener;

use AppBundle\Entity\LocalBusiness\FulfillmentMethod;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;

#[AsEntityListener(event: Events::postPersist, entity: FulfillmentMethod::class)]
#[AsEntityListener(event: Events::preUpdate, entity: FulfillmentMethod::class)]
#[AsEntityListener(event: Events::postUpdate, entity: FulfillmentMethod::class)]
class FulfillmentMethodListener
{
    private array $entityChangeSet = [];

    public function __construct(
        private readonly LoggerInterface $domainEventLogger)
    {
    }

    public function postPersist(FulfillmentMethod $entity, PostPersistEventArgs $args): void
    {
        $this->logIsEnabled($entity);
    }

    public function preUpdate(FulfillmentMethod $entity, PreUpdateEventArgs $args): void
    {
        $this->entityChangeSet = $args->getEntityChangeSet();
    }

    public function postUpdate(FulfillmentMethod $entity, PostUpdateEventArgs $args): void
    {
        if (isset($this->entityChangeSet['enabled'])) {
            $this->logIsEnabled($entity);
        }
    }

    private function logIsEnabled(FulfillmentMethod $entity): void
    {
        if ($entity->isEnabled()) {
            $this->domainEventLogger->info('FulfillmentMethod enabled', [
                'fulfillmentMethod' => $entity->getId()
            ]);
        } else {
            $this->domainEventLogger->info('FulfillmentMethod disabled', [
                'fulfillmentMethod' => $entity->getId()
            ]);
        }
    }
}
