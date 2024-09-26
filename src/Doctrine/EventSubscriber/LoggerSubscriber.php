<?php

namespace AppBundle\Doctrine\EventSubscriber;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;

class LoggerSubscriber implements EventSubscriber
{

    private array $insertions = [];
    private array $updates = [];
    private array $deletions = [];

    public function __construct(
        private readonly LoggerInterface $databaseLogger)
    {
    }

    public function getSubscribedEvents(): array
    {
        return array(
            Events::onFlush,
            Events::postFlush,
        );
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $em = $args->getObjectManager();
        $uow = $em->getUnitOfWork();

        $this->insertions = array_map(fn($entity) => new EntityItem($uow, $entity), $uow->getScheduledEntityInsertions());
        $this->updates = array_map(fn($entity) => new EntityItem($uow, $entity), $uow->getScheduledEntityUpdates());
        $this->deletions = array_map(fn($entity) => new EntityItem($uow, $entity), $uow->getScheduledEntityDeletions());
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        $em = $args->getObjectManager();
        $uow = $em->getUnitOfWork();

        $this->databaseLogger->info(sprintf('Insertions: %d; %s',
            count($this->insertions),
            implode(', ', array_map(fn($entity) => $this->formatEntity($uow, $entity), $this->insertions))));
        $this->databaseLogger->info(sprintf('Updates: %d; %s',
            count($this->updates),
            implode(', ', array_map(fn($entity) => $this->formatEntity($uow, $entity), $this->updates))));
        $this->databaseLogger->info(sprintf('Deletions: %d; %s',
            count($this->deletions),
            implode(', ', array_map(fn($entity) => $this->formatEntity($uow, $entity), $this->deletions))));
    }

    private function formatEntity($unitOfWork, EntityItem $entityItem): string
    {
        return sprintf('%s#%s',
            (new \ReflectionClass($entityItem->entity))->getShortName(),
            implode(',', $entityItem->getDatabaseIdentifier($unitOfWork)));
    }
}

class EntityItem
{
    private array $initialIdentifier;

    public function __construct(
        $unitOfWork,
        public $entity,
    )
    {
        try {
            $this->initialIdentifier = $unitOfWork->getEntityIdentifier($entity);
        } catch (\Exception $e) {
            // happens for entities that are not inserted yet
            $this->initialIdentifier = [];
        }
    }

    public function getDatabaseIdentifier($unitOfWork)
    {
        $isPersisted = count($this->initialIdentifier) !== 0;

        if ($isPersisted) {
            // a way to know the id of an entity that was deleted
            return $this->initialIdentifier;
        } else {
            return $unitOfWork->getEntityIdentifier($this->entity);
        }
    }
}
