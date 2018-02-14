<?php

namespace AppBundle\Service;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Task;
use AppBundle\Entity\TaskAssignment;
use AppBundle\Event\TaskDoneEvent;
use AppBundle\Event\TaskFailedEvent;
use AppBundle\Event\TaskAssignEvent;
use AppBundle\Event\TaskUnassignEvent;
use Doctrine\ORM\Query\Expr;
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

            $taskRepository = $tasks = $this->doctrine->getRepository(Task::class);

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
            $taskToAssign->assignTo($user, $tasksWithPosition[$taskToAssign]);

            $isAssignedToSameUser = $taskToAssign->isAssigned() && $taskToAssign->isAssignedTo($user);
            if (!$isAssignedToSameUser) {
                $this->dispatcher->dispatch(TaskAssignEvent::NAME, new TaskAssignEvent($taskToAssign, $user));
            }
        }
    }

    public function unassign(Task $task)
    {
        $taskRepository = $tasks = $this->doctrine->getRepository(Task::class);

        $linked = $taskRepository->findLinked($task);
        $tasks = array_merge([$task], $linked);

        foreach ($tasks as $taskToUnassign) {

            // FIXME
            // Until the dashboard manages linked tasks properly,
            // TaskManager::unassign may be called twice
            if ($taskToUnassign->isAssigned()) {
                $this->doctrine
                    ->getManagerForClass(TaskAssignment::class)
                    ->remove($taskToUnassign->getAssignment());

                $taskToUnassign->unassign();

                $this->dispatcher->dispatch(TaskUnassignEvent::NAME, new TaskUnassignEvent($taskToUnassign));
            }

        }

        // TODO Reorder assigned tasks
    }

    public function markAsDone(Task $task)
    {
        $task->setStatus(Task::STATUS_DONE);

        $this->dispatcher->dispatch(TaskDoneEvent::NAME, new TaskDoneEvent($task));
    }

    public function markAsFailed(Task $task, $reason = null)
    {
        $task->setStatus(Task::STATUS_FAILED);

        $this->dispatcher->dispatch(TaskFailedEvent::NAME, new TaskFailedEvent($task, $reason));
    }
}
