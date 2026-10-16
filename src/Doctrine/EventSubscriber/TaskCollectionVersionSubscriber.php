<?php

namespace AppBundle\Doctrine\EventSubscriber;

use AppBundle\Entity\Task\VersionedCollectionInterface;
use AppBundle\Entity\TaskCollectionItem;
use AppBundle\Entity\TaskList;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\UnitOfWork;

/**
 * Keeps the optimistic locking version of a task list or a tour moving when
 * the only thing that changed is which items it holds, or in what order.
 *
 * Doctrine increments a version field as part of the UPDATE it issues for a
 * dirty entity. A collection whose items changed has no changeset of its own:
 * adding, removing or reordering an item touches the item rows, not the
 * parent. So the parent would get no UPDATE, and its version would stay put
 * while its contents changed — which is precisely the state a stale client
 * must not be allowed to write over.
 *
 * `TaskList::addItem()` and `clear()` touch no scalar field, and the only code
 * that writes `distance`/`polyline` is `CalculateRouteHandler`, which runs in
 * a separate flush. Within a `set_items` request nothing marks the list dirty,
 * so this is load-bearing rather than defensive.
 *
 * Only `TaskList` and `Tour` are versioned. `Delivery`, the other collection
 * under `TaskCollection`, is deliberately left alone.
 *
 * @see https://github.com/coopcycle/coopcycle-web/issues/5372
 */
#[AsDoctrineListener(event: Events::onFlush, priority: 16, connection: 'default')]
class TaskCollectionVersionSubscriber
{
    public function onFlush(OnFlushEventArgs $args): void
    {
        $em = $args->getObjectManager();
        $uow = $em->getUnitOfWork();

        $parents = new \SplObjectStorage();

        // Added or reordered items still point at their parent.
        $scheduled = array_merge(
            $uow->getScheduledEntityInsertions(),
            $uow->getScheduledEntityUpdates()
        );

        foreach ($scheduled as $entity) {
            if (!$this->isItem($entity)) {
                continue;
            }
            $this->collect($parents, $entity->getParent());
        }

        // A removed item cannot be asked for its parent: `TaskList::clear()`
        // detaches it before orphan removal, so `getParent()` is already null
        // by the time we get here. The data it was loaded with still knows.
        foreach ($uow->getScheduledEntityDeletions() as $entity) {
            if (!$this->isItem($entity)) {
                continue;
            }

            $original = $uow->getOriginalEntityData($entity);
            $this->collect($parents, $original['parent'] ?? $entity->getParent());
        }

        // Belt and braces for any path that replaces the collection wholesale
        // without going through the items themselves.
        $collections = array_merge(
            $uow->getScheduledCollectionUpdates(),
            $uow->getScheduledCollectionDeletions()
        );

        foreach ($collections as $collection) {
            $this->collect($parents, $collection->getOwner());
        }

        foreach ($parents as $parent) {
            $this->touch($em, $uow, $parent);
        }
    }

    private function isItem($entity): bool
    {
        return $entity instanceof TaskList\Item || $entity instanceof TaskCollectionItem;
    }

    private function collect(\SplObjectStorage $parents, $parent): void
    {
        if ($parent instanceof VersionedCollectionInterface) {
            $parents->attach($parent);
        }
    }

    private function touch($em, UnitOfWork $uow, VersionedCollectionInterface $parent): void
    {
        // A collection being created already starts at version 1.
        if ($uow->isScheduledForInsert($parent)) {
            return;
        }

        // Deleting a tour cascades to its items, which brings us here with the
        // tour itself already scheduled for removal. Making it dirty then asks
        // Doctrine to UPDATE a row it is about to DELETE, in that order, and
        // the flush blows up — `DELETE FROM tour` had already removed it.
        if ($uow->isScheduledForDelete($parent)) {
            return;
        }

        $metadata = $em->getClassMetadata(get_class($parent));

        // Touching `updatedAt` is what makes the parent dirty, so that Doctrine
        // issues the UPDATE that carries the version increment. It also makes
        // `updatedAt` honest about membership changes, which it was not before.
        $parent->setUpdatedAt(new \DateTime());

        if (empty($uow->getEntityChangeSet($parent))) {
            $uow->computeChangeSet($metadata, $parent);
        } else {
            $uow->recomputeSingleEntityChangeSet($metadata, $parent);
        }
    }
}
