<?php

namespace AppBundle\Service;

use AppBundle\Domain\Task\Command\AddToGroup;
use AppBundle\Domain\Task\Command\Cancel;
use AppBundle\Domain\Task\Command\DeleteGroup;
use AppBundle\Domain\Task\Command\Incident;
use AppBundle\Domain\Task\Command\MarkAsDone;
use AppBundle\Domain\Task\Command\MarkAsFailed;
use AppBundle\Domain\Task\Command\RemoveFromGroup;
use AppBundle\Domain\Task\Command\Reschedule;
use AppBundle\Domain\Task\Command\Restore;
use AppBundle\Domain\Task\Command\Start;
use AppBundle\Entity\Task;
use AppBundle\Entity\Task\Group as TaskGroup;
use SimpleBus\SymfonyBridge\Bus\CommandBus;

class TaskManager
{
    private $commandBus;

    public function __construct(CommandBus $commandBus)
    {
        $this->commandBus = $commandBus;
    }

    public function cancel(Task $task)
    {
        $this->commandBus->handle(new Cancel($task));
    }

    public function deleteGroup(TaskGroup $taskGroup)
    {
        $this->commandBus->handle(new DeleteGroup($taskGroup));
    }

    public function addToGroup(array $tasks, TaskGroup $taskGroup)
    {
        $this->commandBus->handle(new AddToGroup($tasks, $taskGroup));
    }

    public function removeFromGroup(Task $task)
    {
        $this->commandBus->handle(new RemoveFromGroup($task));
    }

    public function markAsDone(Task $task, $notes = null, $contactName = null)
    {
        $this->commandBus->handle(new MarkAsDone($task, $notes, $contactName));
    }

    public function markAsFailed(Task $task, $notes = null, $contactName = null, $reason = null)
    {
        $this->commandBus->handle(new MarkAsFailed($task, $notes, $contactName, $reason));
    }

    public function start(Task $task)
    {
        $this->commandBus->handle(new Start($task));
    }

    public function restore(Task $task)
    {
        $this->commandBus->handle(new Restore($task));
    }

    public function reschedule(Task $task, \DateTime $rescheduledAfter, \DateTime $rescheduledBefore){
        $this->commandBus->handle(new Reschedule($task, $rescheduledAfter, $rescheduledBefore));
    }

    public function incident(Task $task, string $reason, ?string $notes = null, array $data = []): void
    {
        $this->commandBus->handle(new Incident($task, $reason, $notes, $data));
    }
}
