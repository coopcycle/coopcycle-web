<?php

namespace AppBundle\Service;

use AppBundle\Entity\Incident\Incident;
use AppBundle\Entity\Task;
use AppBundle\Entity\Task\Group as TaskGroup;
use AppBundle\Message\Task\Command\AddToGroup;
use AppBundle\Message\Task\Command\Cancel;
use AppBundle\Message\Task\Command\DeleteGroup;
use AppBundle\Message\Task\Command\Incident as IncidentCommand;
use AppBundle\Message\Task\Command\MarkAsDone;
use AppBundle\Message\Task\Command\MarkAsFailed;
use AppBundle\Message\Task\Command\RemoveFromGroup;
use AppBundle\Message\Task\Command\Reschedule;
use AppBundle\Message\Task\Command\Restore;
use AppBundle\Message\Task\Command\ScanBarcode;
use AppBundle\Message\Task\Command\Start;
use AppBundle\Message\Task\Command\Update;
use Symfony\Component\Messenger\MessageBusInterface;

class TaskManager
{

    public function __construct(
        private MessageBusInterface $commandBus
    ) {}

    public function markAsDone(Task $task, $notes = null, $contactName = null)
    {
        $this->commandBus->dispatch(new MarkAsDone($task, $notes, $contactName));
    }

    public function cancel(Task $task)
    {
        $this->commandBus->dispatch(new Cancel($task));
    }

    public function deleteGroup(TaskGroup $taskGroup)
    {
        $this->commandBus->dispatch(new DeleteGroup($taskGroup));
    }

    public function addToGroup(array $tasks, TaskGroup $taskGroup)
    {
        $this->commandBus->dispatch(new AddToGroup($tasks, $taskGroup));
    }

    public function removeFromGroup(Task $task)
    {
        $this->commandBus->dispatch(new RemoveFromGroup($task));
    }

    public function markAsFailed(Task $task, $notes = null, $contactName = null, $reason = null)
    {
        $this->commandBus->dispatch(new MarkAsFailed($task, $notes, $contactName, $reason));
    }

    public function start(Task $task)
    {
        $this->commandBus->dispatch(new Start($task));
    }

    public function update(Task $task)
    {
        $this->commandBus->dispatch(new Update($task));
    }

    public function restore(Task $task)
    {
        $this->commandBus->dispatch(new Restore($task));
    }

    public function reschedule(Task $task, \DateTime $rescheduledAfter, \DateTime $rescheduledBefore){
        $this->commandBus->dispatch(new Reschedule($task, $rescheduledAfter, $rescheduledBefore));
    }

    public function incident(Task $task, string $reason, ?string $notes = null, array $data = [], Incident $incident = null): void
    {
        $this->commandBus->dispatch(new IncidentCommand($task, $reason, $notes, $data, $incident));
    }

    public function scan(Task $task): void
    {
        $this->commandBus->dispatch(new ScanBarcode($task));
    }
}
