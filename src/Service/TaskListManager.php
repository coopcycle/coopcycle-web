<?php

namespace AppBundle\Service;

use AppBundle\Entity\Task;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;

class TaskListManager {

    public function __construct(
        protected EntityManagerInterface $entityManager,
    ) {}

    public function assign($taskList, $items) {
        $currentTasks = $taskList->getTasks();

        // FIXME ? it seems we have to flush manually to delete the previous TaskList\Item... not sure about it
        // orphanRemoval=true shoudl do the trick but we have to flush for it to work ?
        $taskList->clear();
        $this->entityManager->persist($taskList);
        $this->entityManager->flush();

        $taskList->setItems($items);

        $newTasks = $taskList->getTasks();

        $tasksToRemove = [];
        foreach ($currentTasks as $task) {
            if (!array_search($task, $newTasks)) {
                $tasksToRemove[] = $task;
            }
        }

        // reflect unassignment on the Task objects
        $qb = $this->entityManager->createQueryBuilder();
        $qb->update(Task::class, 't')
            ->set('t.assignedTo', ':assignedTo')
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