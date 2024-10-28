<?php

namespace AppBundle\Doctrine\EventSubscriber;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;

class LoggerSubscriber implements EventSubscriber
{

    private array $entityInsertions = [];
    private array $entityUpdates = [];
    private array $entityDeletions = [];

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

        $this->entityInsertions = array_map(fn($entity) => new EntityItem($uow, $entity), $uow->getScheduledEntityInsertions());
        $this->entityUpdates = array_map(fn($entity) => new EntityItem($uow, $entity), $uow->getScheduledEntityUpdates());
        $this->entityDeletions = array_map(fn($entity) => new EntityItem($uow, $entity), $uow->getScheduledEntityDeletions());
        //TODO; there are also collectionUpdates and collectionDeletions that can be logged
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        $em = $args->getObjectManager();
        $uow = $em->getUnitOfWork();

        $this->log($uow, 'insertions', $this->entityInsertions);
        $this->log($uow, 'updates', $this->entityUpdates);
        $this->log($uow, 'deletions', $this->entityDeletions);
    }

    private function log($uow, string $action, array $list): void
    {
        if (count($list) === 0) {
            return;
        }

        $this->databaseLogger->info(sprintf('Entity %s: %d; %s',
            $action,
            count($list),
            implode(', ', array_map(fn($entity) => $entity->format($uow), $list))));
    }
}

class EntityItem
{
    private array $initialIdentifier;

    public function __construct(
        $unitOfWork,
        private $entity,
    )
    {
        try {
            $this->initialIdentifier = $unitOfWork->getEntityIdentifier($entity);
        } catch (\Exception $e) {
            // happens for entities that are not inserted yet
            $this->initialIdentifier = [];
        }
    }

    private function getDatabaseIdentifier($unitOfWork)
    {
        $isPersisted = count($this->initialIdentifier) !== 0;

        if ($isPersisted) {
            // a way to know the id of an entity that was deleted
            return $this->initialIdentifier;
        } else {
            return $unitOfWork->getEntityIdentifier($this->entity);
        }
    }

    function format($unitOfWork): string
    {
        return sprintf('%s#%s',
            (new \ReflectionClass($this->entity))->getShortName(),
            implode(',', $this->getDatabaseIdentifier($unitOfWork)));
    }
}
