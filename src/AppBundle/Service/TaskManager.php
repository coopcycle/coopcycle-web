<?php

namespace AppBundle\Service;

use AppBundle\Domain\Task\Command\MarkAsDone;
use AppBundle\Domain\Task\Command\MarkAsFailed;
use AppBundle\Entity\Task;
use AppBundle\Exception\PreviousTaskNotCompletedException;
use FOS\UserBundle\Model\UserInterface;
use Doctrine\Common\Persistence\ManagerRegistry;
use SimpleBus\Message\Bus\MessageBus;

class TaskManager
{
    private $doctrine;
    private $commandBus;

    public function __construct(
        ManagerRegistry $doctrine,
        MessageBus $commandBus)
    {
        $this->doctrine = $doctrine;
        $this->commandBus = $commandBus;
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
        $this->commandBus->handle(new MarkAsDone($task, $notes));
    }

    public function markAsFailed(Task $task, $notes = null)
    {
        $this->commandBus->handle(new MarkAsFailed($task, $notes));
    }
}
