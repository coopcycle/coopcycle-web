<?php

namespace AppBundle\Service;

use ApiPlatform\Core\Api\IriConverterInterface;
use AppBundle\Entity\Task;
use AppBundle\Entity\TaskList;
use AppBundle\Entity\TaskList\Item;
use AppBundle\Entity\Tour;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class TaskListManager {

    public function __construct(
        protected EntityManagerInterface $entityManager,
        protected IriConverterInterface $iriConverter,
        protected LoggerInterface $logger
    ) {}

    public function assign(TaskList $taskList, $newItemsIris) {

        $currentItems =  array_merge(array(), $taskList->getItems()->toArray());

        // items that were removed in $newItems will be removed thanks to orphan removal
        $taskList->clear();

        foreach($newItemsIris as $position => $newItemIri) {
            $this->logger->info('match new item IRI ' .$newItemIri);

            $existingItem = array_filter(
                $currentItems,
                function (Item $item) use ($newItemIri) {
                    $this->logger->info('try match with item IRI ' .$newItemIri);
                    return $item->getItemIri($this->iriConverter) === $newItemIri;}
            );
            // update position
            if (count($existingItem) > 0) {
                $this->logger->info('found match for ' .$newItemIri);
                $existingItem = array_shift($existingItem);
                $existingItem->setPosition($position);
                $taskList->addItem($existingItem);
            // items that were added to the tasklist
            } else {
                $this->logger->info('not found match for ' .$newItemIri);
                $taskOrTour = $this->iriConverter->getItemFromIri($newItemIri);
                $item = new Item();
                $item->setPosition($position);
                if ($taskOrTour instanceof Tour) {
                    $item->setTour($taskOrTour);
                } else {
                    $item->setTask($taskOrTour);
                }
                $taskList->addItem($item);
            }
        }

        // Manage tasks (i.e. CASCADE assignations information on task.assignedTo and task.assignedAt)
        $currentTasks = $taskList->getTasks();
        $newTasks = $taskList->getTasks();
        $tasksToRemove = [];
        foreach ($currentTasks as $task) {
            if (!array_search($task, $newTasks)) {
                $tasksToRemove[] = $task;
                // $task->unassign();
            }
        }

        // reflect unassignment on the Task objects
        $qb = $this->entityManager->createQueryBuilder();
        $qb->update(Task::class, 't')
            ->set('t.assignedTo', ':assignedTo') // ALOIS set assignedAT
            ->where('t in (:tasks)')
            ->setParameter('assignedTo', null)
            ->setParameter('tasks', $tasksToRemove)
            ->getQuery()
            ->execute();

        // reflect assignment on the Task objects
        $qb = $this->entityManager->createQueryBuilder();
        $qb->update(Task::class, 't')
            ->set('t.assignedTo', ':assignedTo')
            ->where('t in (:tasks)')
            ->setParameter('assignedTo', $taskList->getCourier())
            ->setParameter('tasks', $newTasks)
            ->getQuery()
            ->execute();
    }

}