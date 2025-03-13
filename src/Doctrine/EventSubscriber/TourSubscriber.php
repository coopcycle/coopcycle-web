<?php

namespace AppBundle\Doctrine\EventSubscriber;

use AppBundle\Domain\Tour\Event\TourCreated;
use AppBundle\Domain\Tour\Event\TourUpdated;
use AppBundle\Entity\TaskCollectionItem;
use AppBundle\Entity\Tour;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;
use SimpleBus\Message\Bus\MessageBus;

class TourSubscriber implements EventSubscriber
{

    private $insertedTours = [];

    private $updatedTours = [];

    public function __construct(
        private LoggerInterface $logger,
        private MessageBus $eventBus
    ) {}

    public function getSubscribedEvents()
    {
        return array(
            Events::onFlush,
            Events::postFlush
        );
    }

    /**
     * Trickles down the assignment information from tour to tasks.
     * The tour is linked to the TaskList as an item, now we want to fill task.assignedTo
     */
    public function onFlush(OnFlushEventArgs $args)
    {

        // init
        $this->insertedTours = [];
        $this->updatedTours = [];

        $em = $args->getEntityManager();
        $uow = $em->getUnitOfWork();

        $entities = array_merge(
            $uow->getScheduledEntityInsertions(),
            $uow->getScheduledEntityUpdates(),
            $uow->getScheduledEntityDeletions()
        );

        $insertedTours = array_filter($uow->getScheduledEntityInsertions(), function ($entity) {
            return $entity instanceof Tour;
        });

        foreach ($insertedTours as $insertedTour) {
            $this->insertedTours[] = $insertedTour;
        }

        $taskCollectionItems = array_filter($entities, function ($entity) {
            return $entity instanceof TaskCollectionItem;
        });

        foreach ($taskCollectionItems as $taskCollectionItem) {
            $taskCollection = $taskCollectionItem->getParent();

            // When a TaskCollectionItem has been removed, its parent is NULL.
            if (!$taskCollection) {
                $entityChangeSet = $uow->getEntityChangeSet($taskCollectionItem);
                [ $oldValue, $newValue ] = $entityChangeSet['parent'];
                $taskCollection = $oldValue;
                $removed = true;
            } else {
                $removed = false;
            }

            if ($taskCollection instanceof Tour) {
                $this->processTourItem($taskCollectionItem, $removed, $taskCollection);
                $this->updatedTours[] = $taskCollection;
            }

        }

        $uow->computeChangeSets();
    }

    private function processTourItem (TaskCollectionItem $taskCollectionItem, bool $removed, Tour $taskCollection) {

        $this->logger->debug(sprintf('Tour modification: processing TaskCollectionItem #%d', $taskCollectionItem->getId()));

        $task = $taskCollectionItem->getTask();
        $item = $taskCollection->getTaskListItem();

        // phpstan struggles with "populating" the inversed side of the one-one - ref https://github.com/phpstan/phpstan-doctrine/issues/244
        /** @phpstan-ignore function.impossibleType */
        if (!is_null($item)) {
            if (!$removed && $task->isAssigned() !== $item->getParent()->getCourier()) { // tour is assigned and the item belongs to it
                $item = $taskCollection->getTaskListItem();
                $taskList = $item->getParent();
                $this->logger->debug(sprintf('Tour modification: Task #%d needs to be assigned', $taskCollectionItem->getTask()->getId()));
                $task->assignTo($taskList->getCourier(), $taskList->getDate());
            } else if ($removed && $task->isAssigned()) { // tour is assigned and the item was removed
                $this->logger->debug(sprintf('Tour modification: Task #%d needs to be unassigned', $taskCollectionItem->getTask()->getId()));
                $taskCollectionItem->getTask()->unassign();
            }
        }
    }

    public function postFlush(PostFlushEventArgs $args) {

        foreach ($this->insertedTours as $insertedTour) {
            $this->eventBus->handle(new TourCreated($insertedTour));
        }

        foreach($this->updatedTours as $updatedTour) {
            $this->eventBus->handle(new TourUpdated($updatedTour));
        }
    }
}
