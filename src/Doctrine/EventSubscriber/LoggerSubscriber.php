<?php

namespace AppBundle\Doctrine\EventSubscriber;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
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
        private readonly LoggerInterface $databaseLogger
    )
    {
    }

    public function getSubscribedEvents(): array
    {
        return array(
            Events::postPersist,
            Events::postUpdate,
            Events::preRemove,
            Events::postFlush,
        );
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        // use postPersist to have access to entity ID
        $this->entityInsertions[] = EntityItem::create($args);
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $this->entityUpdates[] = EntityItem::create($args);
    }

    public function preRemove(PreRemoveEventArgs $args): void
    {
        // use preRemove to have access to entity ID
        $this->entityDeletions[] = EntityItem::create($args);
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        $this->log('insertions', $this->entityInsertions);
        $this->log('updates', $this->entityUpdates);
        $this->log('deletions', $this->entityDeletions);

        $this->entityInsertions = [];
        $this->entityUpdates = [];
        $this->entityDeletions = [];
    }

    /**
     * @param EntityItem[] $list
     */
    private function log(string $action, array $list): void
    {
        if (count($list) === 0) {
            return;
        }

        $this->databaseLogger->info(sprintf('Entities %s: %s',
            $action,
            implode(', ', array_map(fn($entity) => sprintf('%s#%s',
                $entity->getClassName(),
                $entity->getDatabaseIdentifier()), $list))),
            [
                'action' => $action,
                // log the number of entities per class
                'entities' => array_reduce($list, function ($carry, $item) {
                    $className = $item->getClassName();
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
    static function create(LifecycleEventArgs $args): EntityItem
    {
        $em = $args->getObjectManager();
        $uow = $em->getUnitOfWork();

        $entity = $args->getObject();

        $className = (new ReflectionClass($entity))->getShortName();

        try {
            $identifier = $uow->getEntityIdentifier($entity);
        } catch (Exception $e) {
            // should not happen (could happen if called before an entity is inserted)
            $identifier = [];
        }

        return new self($className, $identifier);
    }

    public function __construct(
        private readonly string $className,
        private readonly array $identifier
    )
    {
    }

    function getClassName(): string
    {
        return $this->className;
    }

    function getDatabaseIdentifier(): string
    {
        return implode(',', $this->identifier);
    }
}
