<?php

namespace AppBundle\Doctrine\EventSubscriber;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\UnitOfWork;
use Exception;
use Psr\Log\LoggerInterface;
use ReflectionClass;

class LoggerSubscriber implements EventSubscriber
{

    /** @var EntityItem[] */
    private array $entityInsertions = [];
    /** @var EntityItem[] */
    private array $entityUpdates = [];
    /** @var EntityItem[] */
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

        $this->entityInsertions = array_merge($this->entityInsertions, array_map(fn($entity) => new EntityItem($uow, $entity), $uow->getScheduledEntityInsertions()));
        $this->entityUpdates = array_merge($this->entityUpdates, array_map(fn($entity) => new EntityItem($uow, $entity), $uow->getScheduledEntityUpdates()));
        $this->entityDeletions = array_merge($this->entityDeletions, array_map(fn($entity) => new EntityItem($uow, $entity), $uow->getScheduledEntityDeletions()));
        //TODO; there are also collectionUpdates and collectionDeletions that can be logged
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        $em = $args->getObjectManager();
        $uow = $em->getUnitOfWork();

        $this->log($uow, 'insertions', $this->entityInsertions);
        $this->log($uow, 'updates', $this->entityUpdates);
        $this->log($uow, 'deletions', $this->entityDeletions);

        $this->entityInsertions = [];
        $this->entityUpdates = [];
        $this->entityDeletions = [];
    }

    /**
     * @param EntityItem[] $list
     */
    private function log(UnitOfWork $uow, string $action, array $list): void
    {
        if (count($list) === 0) {
            return;
        }

        $entities = array_map(fn($entity) => [
            'class' => $entity->getClassName(),
            'id' => implode(',', $entity->getDatabaseIdentifier($uow)),
        ], $list);

        $this->databaseLogger->info(sprintf('Entities %s: %s',
            $action,
            implode(', ', array_map(fn($entity) => sprintf('%s#%s',
                $entity['class'],
                $entity['id']), $entities))),
            [
                'action' => $action,
                'entities' => array_reduce($entities, function ($carry, $item) {
                    $className = $item['class'];
                    if (!isset($carry[$className])) {
                        $carry[$className] = 0;
                    }
                    $carry[$className]++;
                    return $carry;
                }, []),
            ]);
    }
}

class EntityItem
{
    private array $initialIdentifier;

    public function __construct(
        UnitOfWork $unitOfWork,
        private $entity,
    )
    {
        try {
            $this->initialIdentifier = $unitOfWork->getEntityIdentifier($entity);
        } catch (Exception $e) {
            // happens for entities that are not inserted yet
            $this->initialIdentifier = [];
        }
    }

    function getClassName(): string
    {
        return (new ReflectionClass($this->entity))->getShortName();
    }

    function getDatabaseIdentifier($unitOfWork): array
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
