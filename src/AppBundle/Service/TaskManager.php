<?php

namespace AppBundle\Service;

use AppBundle\Entity\Task;
use AppBundle\Entity\TaskAssignment;
use AppBundle\Event\TaskDoneEvent;
use AppBundle\Event\TaskFailedEvent;
use AppBundle\Event\TaskAssignEvent;
use AppBundle\Event\TaskUnassignEvent;
use FOS\UserBundle\Model\UserInterface;
use Doctrine\Common\Persistence\ManagerRegistry;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class TaskManager
{
    private $doctrine;
    private $dispatcher;

    public function __construct(ManagerRegistry $doctrine, EventDispatcherInterface $dispatcher)
    {
        $this->doctrine = $doctrine;
        $this->dispatcher = $dispatcher;
    }

    /**
     * Assigns a task to a user.
     * If $position is omitted, $task will be assigned at the end.
     * If $task is linked to other tasks, they will be assigned to the same user.
     *
     * @param Task          $task
     * @param UserInterface $user
     * @param int           $position
     */
    public function assign(Task $task, UserInterface $user, $position = null)
    {
        $tasksWithPosition = new \SplObjectStorage();

        if (null === $position) {

            $taskRepository = $this->doctrine->getRepository(Task::class);

            $tasks = $taskRepository->findByUserAndDate($user, $task->getDoneBefore());
            if (count($tasks) === 0) {
                $position = 0;
            } else {
                $positions = array_map(function (Task $task) {
                    return $task->getAssignment()->getPosition();
                }, $tasks);
                $position = max($positions) + 1;
            }

            $linked = $taskRepository->findLinked($task);
            $tasks = array_merge([$task], $linked);

            usort($tasks, function (Task $a, Task $b) {
                if ($a->hasPrevious() && $a->getPrevious() === $b) {
                    return 1;
                }
                if ($b->hasPrevious() && $b->getPrevious() === $a) {
                    return -1;
                }
                return 0;
            });

            foreach ($tasks as $taskToAssign) {
                $tasksWithPosition[$taskToAssign] = $position++;
            }

        } else {
            $tasksWithPosition[$task] = $position;
        }

        foreach ($tasksWithPosition as $taskToAssign) {

            $wasAssignedToSameUser = $taskToAssign->isAssignedTo($user);
            $taskToAssign->assignTo($user, $tasksWithPosition[$taskToAssign]);

            if (!$wasAssignedToSameUser) {
                $this->dispatcher->dispatch(TaskAssignEvent::NAME, new TaskAssignEvent($taskToAssign, $user));
            }
        }
    }

    public function unassign(Task $task)
    {
        $taskRepository = $this->doctrine->getRepository(Task::class);

        $linked = $taskRepository->findLinked($task);
        $tasks = array_merge([$task], $linked);

        foreach ($tasks as $taskToUnassign) {

            // FIXME
            // Until the dashboard manages linked tasks properly,
            // TaskManager::unassign may be called twice
            if ($taskToUnassign->isAssigned()) {
                $freedUser = clone $taskToUnassign->getAssignment()->getCourier();

                $this->doctrine
                    ->getManagerForClass(TaskAssignment::class)
                    ->remove($taskToUnassign->getAssignment());

                $taskToUnassign->unassign();

                $this->dispatcher->dispatch(
                    TaskUnassignEvent::NAME,
                    new TaskUnassignEvent($taskToUnassign, $freedUser));
            }

        }

        // TODO Reorder assigned tasks
    }

    public function remove(Task $task)
    {
        if ($task->isAssigned()) {
            throw new \Exception(sprintf('Task #%d is assigned to %s. Only unassigned tasks can be removed.',
                $task->getId(), $task->getAssignedCourier->getUsername()));
        }

        $taskRepository = $this->doctrine->getRepository(Task::class);
        $entityManager = $this->doctrine->getManagerForClass(Task::class);

        $nextTasks = $taskRepository->findBy(['previous' => $task]);
        foreach ($nextTasks as $nextTask) {
            $nextTask->setPrevious(null);
        }

        $entityManager->remove($task);
    }

    public function markAsDone(Task $task, $notes = null)
    {
        $task->setStatus(Task::STATUS_DONE);

        $this->dispatcher->dispatch(TaskDoneEvent::NAME, new TaskDoneEvent($task, $task->getAssignment()->getCourier(), $notes));
    }

    public function markAsFailed(Task $task, $notes = null)
    {
        $task->setStatus(Task::STATUS_FAILED);

        $this->dispatcher->dispatch(TaskFailedEvent::NAME, new TaskFailedEvent($task, $task->getAssignment()->getCourier(), $notes));
    }
}
