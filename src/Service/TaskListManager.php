<?php

namespace AppBundle\Service;

use ApiPlatform\Api\IriConverterInterface;
use AppBundle\Entity\Task;
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
        foreach ($currentTasks as $task) {
            // NB: use a strict in_array() and NOT array_search(), whose result
            // (index 0 for the first element) is falsy and would misclassify it.
            if (in_array($task, $newTasks, true)) {
                continue;
            }

            // A completed task (DONE/FAILED) must never be unassigned by a
            // set_items call that omits it: the work is already finished and
            // clients may legitimately not send completed tasks back (see #5249).
            // Preserve the assignment and, when possible, its place in the list.
            if ($task->isCompleted()) {
                $item = $this->findItemForTask($currentItems, $task);
                if (null !== $item) {
                    $item->setPosition($taskList->getItems()->count());
                    $taskList->addItem($item);
                }
                continue;
            }

            $task->unassign();
        }

        // Re-assign every task currently in the list (this includes the
        // completed tasks preserved above).
        foreach ($taskList->getTasks() as $task) {
            $task->assignTo($taskList->getCourier());
        }
    }

    /**
     * Find, among a list of items, the one directly pointing to the given task
     * (i.e. not a task nested inside a tour).
     */
    private function findItemForTask(array $items, Task $task): ?Item
    {
        foreach ($items as $item) {
            if ($item->getTask() === $task) {
                return $item;
            }
        }

        return null;
    }
}
