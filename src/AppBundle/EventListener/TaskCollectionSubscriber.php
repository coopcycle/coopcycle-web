<?php

namespace AppBundle\EventListener;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Task\CollectionInterface as TaskCollectionInterface;
use AppBundle\Entity\TaskCollection;
use AppBundle\Entity\TaskCollectionItem;
use AppBundle\Event\TaskCollectionChangeEvent;
use AppBundle\Service\RoutingInterface;
use Doctrine\Common\EventSubscriber;
use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class TaskCollectionSubscriber implements EventSubscriber
{
    private $dispatcher;
    private $routing;
    private $logger;

    public function __construct(EventDispatcherInterface $dispatcher, RoutingInterface $routing, LoggerInterface $logger)
    {
        $this->dispatcher = $dispatcher;
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
            $data = $this->routing->getServiceResponse('route', $coordinates, [
                'steps' => 'true',
                'overview' => 'full'
            ]);
            $taskCollection->setDistance((int) $data['routes'][0]['distance']);
            $taskCollection->setDuration((int) $data['routes'][0]['duration']);
            $taskCollection->setPolyline($data['routes'][0]['geometry']);
        }
    }

    public function prePersist(LifecycleEventArgs $args)
    {
        $entity = $args->getObject();

        if ($entity instanceof TaskCollectionInterface) {
            $this->calculate($entity);
            $this->dispatcher->dispatch(new TaskCollectionChangeEvent($entity), TaskCollectionChangeEvent::NAME);
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
            $uow->recomputeSingleEntityChangeSet($em->getClassMetadata(TaskCollection::class), $taskCollection);
        }
    }
}
