<?php

namespace AppBundle\Service;

use AppBundle\Domain\Task\Command\Incident as IncidentCommand;
use AppBundle\Domain\Task\Command\RemoveFromGroup;
use AppBundle\Domain\Task\Command\Reschedule;
use AppBundle\Domain\Task\Command\Restore;
use AppBundle\Domain\Task\Command\ScanBarcode;
use AppBundle\Entity\Incident\Incident;
use AppBundle\Entity\Task;
use AppBundle\Entity\Task\Group as TaskGroup;
use AppBundle\Message\Task\Command\AddToGroup;
use AppBundle\Message\Task\Command\Cancel;
use AppBundle\Message\Task\Command\DeleteGroup;
use AppBundle\Message\Task\Command\MarkAsDone;
use AppBundle\Message\Task\Command\MarkAsFailed;
use AppBundle\Message\Task\Command\Start;
use AppBundle\Message\Task\Command\Update;
use Symfony\Component\Messenger\MessageBusInterface;

class TaskManager
{

    public function __construct(
        private MessageBusInterface $commandnewBus
    ) {}

    public function markAsDone(Task $task, $notes = null, $contactName = null)
    {
        $this->commandnewBus->dispatch(new MarkAsDone($task, $notes, $contactName));
    }

    public function cancel(Task $task)
    {
        $this->commandnewBus->dispatch(new Cancel($task));
    }

    public function deleteGroup(TaskGroup $taskGroup)
    {
        $this->commandnewBus->dispatch(new DeleteGroup($taskGroup));
    }

    public function addToGroup(array $tasks, TaskGroup $taskGroup)
    {
        $this->commandnewBus->dispatch(new AddToGroup($tasks, $taskGroup));
    }

    public function removeFromGroup(Task $task)
    {
        $this->commandBus->handle(new RemoveFromGroup($task));
    }

    public function markAsFailed(Task $task, $notes = null, $contactName = null, $reason = null)
    {
        $this->commandnewBus->dispatch(new MarkAsFailed($task, $notes, $contactName, $reason));
    }

    public function start(Task $task)
    {
        $this->commandnewBus->dispatch(new Start($task));
    }

    public function update(Task $task)
    {
        $this->commandnewBus->dispatch(new Update($task));
    }

    public function restore(Task $task)
    {
        $this->commandBus->handle(new Restore($task));
    }

    public function reschedule(Task $task, \DateTime $rescheduledAfter, \DateTime $rescheduledBefore){
        $this->commandBus->handle(new Reschedule($task, $rescheduledAfter, $rescheduledBefore));
    }

    public function incident(Task $task, string $reason, ?string $notes = null, array $data = [], Incident $incident = null): void
    {
        $this->commandBus->handle(new IncidentCommand($task, $reason, $notes, $data, $incident));
    }

    public function scan(Task $task): void
    {
        $this->commandBus->handle(new ScanBarcode($task));
    }
}
