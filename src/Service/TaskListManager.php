<?php

namespace AppBundle\Service;

use ApiPlatform\Core\Api\IriConverterInterface;
use AppBundle\Entity\Task;
use AppBundle\Entity\TaskList;
use AppBundle\Entity\TaskList\Item;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;

class TaskListManager {

    public function __construct(
        protected EntityManagerInterface $entityManager,
        protected IriConverterInterface $iriConverterInterface
    ) {}

    public function assign(TaskList $taskList, $newItems) {

        $currentItems = $taskList->getItems();

        // items that were removed will be removed thanks to orphan removal
        $taskList->clear();

        foreach($newItems as $position => $newItem) {
            $existingItem = array_filter(
                $currentItems->toArray(),
                function (Item $item) use ($newItem) {
                    return $item->getItemIri($this->iriConverterInterface) === $newItem->getItemIri($this->iriConverterInterface);}
            );
            if (count($existingItem)) {
                $existingItem = $existingItem[0];
                $existingItem->setPosition($position);
                $taskList->addItem($existingItem);
            } else { // items that were added to the tasklist
                $taskList->addItem($newItem);
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

        // foreach ($newTasks as $task) {
        //     $task->assignTo(
        //         $taskList->getCourier(),
        //         $taskList->getDate()
        //     );
        // }

        // FIXME this is not reflected in $uow->getScheduledEntityUpdates() in TaskSubscriber
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