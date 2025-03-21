<?php

namespace AppBundle\Doctrine\EventSubscriber;

use AppBundle\Domain\Tour\Event\TourUpdated;
use AppBundle\Entity\TaskCollectionItem;
use AppBundle\Entity\Tour;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Exception;
use Psr\Log\LoggerInterface;
use SimpleBus\Message\Bus\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;

class TourSubscriber implements EventSubscriber
{
    private $logger;
    private $tours = [];

    public function __construct(
        LoggerInterface $logger,
        private readonly MessageBus $eventBus,
        private readonly MessageBusInterface $messageBus,
    )
    {
        $this->logger = $logger;
    }

    public function getSubscribedEvents()
    {
        return array(
            Events::onFlush,
            Events::postFlush,
        );
    }

    /**
     * Trickles down the assignment information from tour to tasks.
     * The tour is linked to the TaskList as an item, now we want to fill task.assignedTo
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
            }

        }

        $uow->computeChangeSets();
    }

    public function postFlush(PostFlushEventArgs $args)
    {
        $this->logger->debug(sprintf('Tours updated = %d', count($this->tours)));
        try {
            $message = new TourUpdated();
            $this->eventBus->handle($message);
        }
        catch (Exception $e) {
            var_dump($e);
        }

        // if (count($this->tours) === 0) {
        //     return;
        // }

        // foreach ($this->tours as $tour) {
        //     $myTaskListDto = $this->taskListRepository->findMyTaskListAsDto($taskList->getCourier(), $taskList->getDate());
        //     $this->eventBus->handle(new TaskListUpdated($taskList->getCourier(), $myTaskListDto));
        //     $this->eventBus->handle(new TaskListUpdatedv2($taskList));

        //     $date = $taskList->getDate();
        //     $users = isset($usersByDate[$date]) ? $usersByDate[$date] : [];

        //     $usersByDate[$date] = array_merge($users, [
        //         $taskList->getCourier()
        //     ]);
        // }
    }

    private function addTour(Tour $tour) {
        // WARNING
        // Do not use in_array() or array_search()
        // It causes error "Nesting level too deep - recursive dependency?"
        $oid = spl_object_hash($tour);
        if (!isset($this->taskLists[$oid])) {
            $this->tours[$oid] = $tour;
        }
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

        if ($taskCollectionItem instanceof Tour) {
            $this->addTour($taskCollectionItem);
        }
    }
}
