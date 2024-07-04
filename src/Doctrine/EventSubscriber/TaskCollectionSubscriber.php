<?php

namespace AppBundle\Doctrine\EventSubscriber;

use AppBundle\Entity\Task\CollectionInterface as TaskCollectionInterface;
use AppBundle\Entity\TaskCollection;
use AppBundle\Entity\TaskCollectionItem;
use AppBundle\Service\RoutingInterface;
use Doctrine\Common\EventSubscriber;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;
use SimpleBus\Message\Bus\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class TaskCollectionSubscriber implements EventSubscriber
{
    private $routing;
    private $logger;

    public function __construct(
        RoutingInterface $routing,
        LoggerInterface $logger)
    {
        $this->routing = $routing;
        $this->logger = $logger;
    }

    public function getSubscribedEvents()
    {
        return array(
            Events::prePersist,
            Events::onFlush
        );
    }

    private function calculate(TaskCollectionInterface $taskCollection)
    {
        $coordinates = [];
        foreach ($taskCollection->getTasks() as $task) {
            $coordinates[] = $task->getAddress()->getGeo();
        }

        if (count($coordinates) <= 1) {
            $taskCollection->setDistance(0);
            $taskCollection->setDuration(0);
            $taskCollection->setPolyline('');
        } else {
            $taskCollection->setDistance($this->routing->getDistance(...$coordinates));
            $taskCollection->setDuration($this->routing->getDuration(...$coordinates));
            $taskCollection->setPolyline($this->routing->getPolyline(...$coordinates));
        }
    }

    public function prePersist(LifecycleEventArgs $args)
    {
        $entity = $args->getObject();

        if ($entity instanceof TaskCollectionInterface) {
            $this->calculate($entity);
        }
    }

    /**
     * Performs TaskCollection calculations when items have been modified.
     */
    public function onFlush(OnFlushEventArgs $args)
    {
        $em = $args->getEntityManager();
        $uow = $em->getUnitOfWork();

        $entities = array_merge(
            $uow->getScheduledEntityInsertions(),
            $uow->getScheduledEntityUpdates(),
            $uow->getScheduledEntityDeletions()
        );

        $taskCollectionItems = array_filter($entities, function ($entity) {
            return $entity instanceof TaskCollectionItem;
        });

        $taskCollections = [];
        foreach ($taskCollectionItems as $taskCollectionItem) {

            $taskCollection = $taskCollectionItem->getParent();

            // When a TaskCollectionItem has been removed, its parent is NULL.
            if (!$taskCollection) {
                $entityChangeSet = $uow->getEntityChangeSet($taskCollectionItem);
                [ $oldValue, $newValue ] = $entityChangeSet['parent'];
                $taskCollection = $oldValue;
            }

            // WARNING
            // Do not use in_array() or array_search()
            // It causes error "Nesting level too deep - recursive dependency?"
            $oid = spl_object_hash($taskCollection);
            if (!isset($taskCollections[$oid])) {
                $taskCollections[$oid] = $taskCollection;
            }
        }

        foreach ($taskCollections as $taskCollection) {

            $this->logger->debug('TaskCollection was modified, recalculating…');
            $this->calculate($taskCollection);

            if ($em->contains($taskCollection)) {
                $uow->recomputeSingleEntityChangeSet($em->getClassMetadata(TaskCollection::class), $taskCollection);
            }
        }
    }
}
