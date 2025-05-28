<?php

namespace AppBundle\Service;

use ApiPlatform\Api\IriConverterInterface;
use AppBundle\Entity\TaskList;
use AppBundle\Entity\TaskList\Item;
use AppBundle\Entity\Tour;
use Doctrine\ORM\EntityManagerInterface;

class TaskListManager {

    public function __construct(
        protected EntityManagerInterface $entityManager,
        protected IriConverterInterface $iriConverter,
    ) {}

    /*
        Assign items (tours and tasks). Works like a PUT, i.e. remove items non-present in $newItemsIris.
    */
    public function assign(TaskList $taskList, $newItemsIris) {

        $currentItems =  array_merge(array(), $taskList->getItems()->toArray());
        $currentTasks = array_merge(array(), $taskList->getTasks());

        // items that were removed in $newItems will be removed thanks to orphan removal
        $taskList->clear();

        foreach($newItemsIris as $position => $newItemIri) {
            $existingItem = array_filter(
                $currentItems,
                function (Item $item) use ($newItemIri) {
                    return $item->getItemIri($this->iriConverter) === $newItemIri;}
            );
            // update position
            if (count($existingItem) > 0) {
                $existingItem = array_shift($existingItem);
                $existingItem->setPosition($position);
                $taskList->addItem($existingItem);
            // items that were added to the tasklist
            } else {
                $taskOrTour = $this->iriConverter->getResourceFromIri($newItemIri);
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

        // Update tasks (i.e. CASCADE assignations information on task.assignedTo)
        // we need to iterate over all the tasks so we trigger EntityChangeSetProcessor - it doesn't seem that the more efficient : $qb = $this->entityManager->createQueryBuilder(->update(Task::class, 't') updates the code
        $newTasks = $taskList->getTasks();
        $tasksToRemove = [];
        foreach ($currentTasks as $task) {
            if (!array_search($task, $newTasks)) {
                $tasksToRemove[] = $task;
                $task->unassign();
            }
        }

        foreach ($newTasks as $task) {
            $task->assignTo($taskList->getCourier(), $taskList->getDate());
        }
    }
}
