<?php

namespace AppBundle\Service;

use AppBundle\Entity\Task;
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

    public function remove(Task $task)
    {
        if ($task->isAssigned()) {
            throw new \Exception(sprintf('Task #%d is assigned to %s. Only unassigned tasks can be removed.',
                $task->getId(), $task->getAssignedCourier()->getUsername()));
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

        $this->dispatcher->dispatch(TaskDoneEvent::NAME, new TaskDoneEvent($task, $task->getAssignedCourier(), $notes));
    }

    public function markAsFailed(Task $task, $notes = null)
    {
        $task->setStatus(Task::STATUS_FAILED);

        $this->dispatcher->dispatch(TaskFailedEvent::NAME, new TaskFailedEvent($task, $task->getAssignedCourier(), $notes));
    }
}
