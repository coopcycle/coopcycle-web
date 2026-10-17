<?php

namespace AppBundle\Service;

use ApiPlatform\Api\IriConverterInterface;
use AppBundle\Entity\Task;
use AppBundle\Entity\TaskList;
use AppBundle\Entity\TaskList\Item;
use AppBundle\Entity\Tour;
use AppBundle\Exception\ItemBelongsToTourException;
use AppBundle\Exception\OrderChangesMembershipException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class TaskListManager {

    private LoggerInterface $logger;

    public function __construct(
        protected EntityManagerInterface $entityManager,
        protected IriConverterInterface $iriConverter,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    /*
        Assign items (tours and tasks). Works like a PUT, i.e. remove items non-present in $newItemsIris.
    */
    public function assign(TaskList $taskList, $newItemsIris) {

        $newItemsIris = $this->rejectItemsFromOtherDays($taskList, $newItemsIris);

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
     * Put items on a list without disturbing the ones already there.
     *
     * This is the delta counterpart of `assign()`. The caller names what it
     * wants added or moved, and nothing else is touched, so a client working
     * from a stale read can no longer remove work it never knew about — the
     * property that makes the whole-list PUT unsafe.
     *
     * An item already held by another list is moved rather than duplicated,
     * in this same flush, so a drag from one courier to another cannot leave
     * the task on both or on neither.
     *
     * @param string[] $itemIris IRIs of tasks and tours
     * @param int|null $position where to insert them; appended when absent
     *
     * @see https://github.com/coopcycle/coopcycle-web/issues/5372
     */
    public function addItems(TaskList $taskList, array $itemIris, ?int $position = null): void
    {
        $itemIris = $this->rejectItemsFromOtherDays($taskList, $itemIris);

        if (0 === count($itemIris)) {
            return;
        }

        // An ordered snapshot to splice into. Positions are rewritten at the
        // end, so they stay dense however the list was ordered before.
        $ordered = $taskList->getItems()->toArray();
        usort($ordered, fn (Item $a, Item $b) => $a->getPosition() <=> $b->getPosition());

        $incoming = [];

        foreach ($itemIris as $itemIri) {
            $taskOrTour = $this->iriConverter->getResourceFromIri($itemIri);

            $existing = $this->findItemFor($ordered, $taskOrTour);

            if (null !== $existing) {
                // Already on this list: naming it again is a move, not an add.
                $ordered = array_values(array_filter($ordered, fn (Item $item) => $item !== $existing));
                $incoming[] = $existing;

                continue;
            }

            $incoming[] = $this->takeFromCurrentList($taskList, $taskOrTour);
        }

        $at = null === $position
            ? count($ordered)
            : max(0, min($position, count($ordered)));

        array_splice($ordered, $at, 0, $incoming);

        foreach ($ordered as $index => $item) {
            $item->setPosition($index);

            if ($item->getParent() !== $taskList) {
                $taskList->addItem($item);
            }
        }

        // Cascade the assignment the way `assign()` does. For a task that came
        // from another courier this is also what takes it off their list, via
        // `EntityChangeSetProcessor`.
        foreach ($taskList->getTasks() as $task) {
            $task->assignTo($taskList->getCourier());
        }
    }

    /**
     * Rewrite the order of a list, and nothing else.
     *
     * The last whole-list write left, and the only one that is safe to keep:
     * it is refused outright if the set of items it names differs in any way
     * from what is on the list. A client whose board has drifted therefore
     * cannot express a removal here at all — it gets told its payload does not
     * describe this list, instead of silently unassigning the difference.
     *
     * @param string[] $itemIris every item on the list, in the wanted order
     *
     * @throws OrderChangesMembershipException if the payload adds, drops or
     *                                         repeats anything
     *
     * @see https://github.com/coopcycle/coopcycle-web/issues/5372
     */
    public function reorder(TaskList $taskList, array $itemIris): void
    {
        $current = [];

        foreach ($taskList->getItems() as $item) {
            $current[$item->getItemIri($this->iriConverter)] = $item;
        }

        $submitted = array_values($itemIris);

        $dropped = array_values(array_diff(array_keys($current), $submitted));
        $added = array_values(array_diff($submitted, array_keys($current)));

        // The counts also catch a payload that repeats an item, and a list
        // holding the same task twice — which is corrupt, and not something to
        // renumber blindly.
        if (count($dropped) > 0 || count($added) > 0 || count($submitted) !== $taskList->getItems()->count()) {
            throw new OrderChangesMembershipException(sprintf(
                'This changes which items are on the list, which ordering does not do%s%s. '
                .'Use POST /api/task_lists/{id}/items and DELETE /api/task_lists/{id}/items/{type}/{itemId}.',
                count($added) > 0 ? sprintf('; not on the list: %s', implode(', ', $added)) : '',
                count($dropped) > 0 ? sprintf('; missing from the payload: %s', implode(', ', $dropped)) : ''
            ));
        }

        foreach ($submitted as $position => $itemIri) {
            $current[$itemIri]->setPosition($position);
        }
    }

    /**
     * Take one item off a list, naming it.
     *
     * The counterpart of `addItems()`, and the other half of what makes a
     * stale client harmless: removal stops being expressible by omission, so
     * saying nothing about a task can no longer unassign it.
     *
     * Scoped to this list on purpose. If the task has since moved to another
     * courier it is no longer an item here, and the call does nothing rather
     * than reaching across and unassigning it from whoever holds it now —
     * which is the same lost update in a different shape.
     *
     * @param Task|Tour $taskOrTour
     *
     * @throws ItemBelongsToTourException if the task is on the list through a
     *                                    tour, which has to be changed as a tour
     *
     * @see https://github.com/coopcycle/coopcycle-web/issues/5372
     */
    public function removeItem(TaskList $taskList, $taskOrTour): void
    {
        $ordered = $taskList->getItems()->toArray();
        usort($ordered, fn (Item $a, Item $b) => $a->getPosition() <=> $b->getPosition());

        $item = $this->findItemFor($ordered, $taskOrTour);

        if (null === $item) {
            if ($taskOrTour instanceof Task) {
                $this->assertNotHeldByATour($ordered, $taskOrTour);
            }

            // Already gone, or never here. Nothing to do, and nothing to
            // report: a client asking twice, or asking about a task that has
            // moved on, is not an error.
            return;
        }

        $taskList->getItems()->removeElement($item);
        $item->setParent(null);

        // Nothing syncs `assignedTo` back from the list, so a task left
        // assigned here would be assigned to a courier whose list it is not
        // on — the "invisible task" state that #874 and
        // `Version20260901090000` had to repair.
        foreach ($this->tasksOf($taskOrTour) as $task) {
            $task->unassign();
        }

        $remaining = array_values(array_filter($ordered, fn (Item $i) => $i !== $item));

        foreach ($remaining as $index => $remainingItem) {
            $remainingItem->setPosition($index);
        }
    }

    /**
     * A task on the list through a tour is not an item of the list, so there
     * is nothing here to remove. Silently doing nothing would read to the
     * dispatcher as the board ignoring them, so say what to do instead.
     *
     * @param Item[] $items
     */
    private function assertNotHeldByATour(array $items, Task $task): void
    {
        foreach ($items as $item) {
            $tour = $item->getTour();

            if (null === $tour) {
                continue;
            }

            if (in_array($task, $tour->getTasks(), true)) {
                throw new ItemBelongsToTourException(sprintf(
                    'Task %d is on this list through tour %d; change the tour instead',
                    $task->getId(),
                    $tour->getId()
                ));
            }
        }
    }

    /**
     * The tasks a list item stands for: a tour brings all of its own.
     *
     * @param Task|Tour $taskOrTour
     *
     * @return Task[]
     */
    private function tasksOf($taskOrTour): array
    {
        return $taskOrTour instanceof Tour ? $taskOrTour->getTasks() : [$taskOrTour];
    }

    /**
     * An item for this task or tour, taken off whatever list currently holds
     * it so that it cannot end up on two.
     */
    private function takeFromCurrentList(TaskList $taskList, $taskOrTour): Item
    {
        if ($taskOrTour instanceof Tour) {
            // A tour travels as a single item, and nothing syncs its
            // membership from its tasks the way `EntityChangeSetProcessor`
            // does for a task. Re-parent the existing item rather than
            // deleting and recreating it: `task_list_item.tour_id` is unique,
            // and Doctrine writes inserts before deletes.
            $item = $taskOrTour->getTaskListItem();

            if (null !== $item) {
                return $item;
            }

            $item = new Item();
            $item->setTour($taskOrTour);

            return $item;
        }

        // `EntityChangeSetProcessor` takes the task off the previous courier's
        // list when `assignedTo` changes, but it sees no change when the task
        // merely moves between two days of the *same* courier, so that case is
        // handled here.
        $currentList = $this->entityManager->getRepository(TaskList::class)
            ->findTaskListContainingTask($taskOrTour, $taskList->getCourier());

        if (null !== $currentList && $currentList !== $taskList) {
            $currentList->removeTask($taskOrTour);
        }

        $item = new Item();
        $item->setTask($taskOrTour);

        return $item;
    }

    /**
     * The item on this list standing for a given task or tour, if any.
     *
     * @param Item[] $items
     */
    private function findItemFor(array $items, $taskOrTour): ?Item
    {
        foreach ($items as $item) {
            if ($taskOrTour instanceof Tour) {
                if ($item->getTour() === $taskOrTour) {
                    return $item;
                }

                continue;
            }

            if ($item->getTask() === $taskOrTour) {
                return $item;
            }
        }

        return null;
    }

    /**
     * A task list is a courier's planning for one specific day, so an item may
     * only be filed on it if the task can actually be carried out that day:
     * the list's date must fall inside the task's [doneAfter, doneBefore]
     * window. This is the same rule the API applies when listing the tasks of
     * a day, @see \AppBundle\Api\Filter\TaskDateFilter.
     *
     * A client that is out of sync (a dispatch screen left on another day, say)
     * can otherwise file tomorrow's task on today's list. The task then has
     * `assignedTo` set but no item on the list of the day it shows up on, and
     * vanishes from the dispatch board altogether: it counts as neither
     * unassigned nor part of any visible task list, and the only way to find it
     * is the search box.
     *
     * Such items are dropped rather than rejected outright, so the rest of the
     * assignment still goes through: the task simply stays unassigned, visible
     * on the board for its own day, instead of disappearing. The warning is
     * there to identify the client that sent it.
     */
    private function rejectItemsFromOtherDays(TaskList $taskList, array $newItemsIris): array
    {
        $date = $taskList->getDate()->format('Y-m-d');

        return array_values(array_filter($newItemsIris, function ($newItemIri) use ($taskList, $date) {

            $taskOrTour = $this->iriConverter->getResourceFromIri($newItemIri);

            // Tours carry their own date; only tasks are checked here.
            if (!$taskOrTour instanceof Task) {
                return true;
            }

            if ($this->isOnDate($taskOrTour, $date)) {
                return true;
            }

            $this->logger->warning(sprintf(
                'Skipping task %s [%s -> %s] on %s\'s task list for %s: it cannot be carried out that day',
                $newItemIri,
                $taskOrTour->getAfter()->format('Y-m-d'),
                $taskOrTour->getBefore()->format('Y-m-d'),
                $taskList->getCourier()->getUsername(),
                $date
            ));

            return false;
        }));
    }

    /**
     * Can $task be carried out on $date (Y-m-d)? A task with an open-ended
     * window belongs to no day in particular, and is left alone.
     */
    private function isOnDate(Task $task, string $date): bool
    {
        $after = $task->getAfter();
        $before = $task->getBefore();

        if (null === $after || null === $before) {
            return true;
        }

        return $date >= $after->format('Y-m-d') && $date <= $before->format('Y-m-d');
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
