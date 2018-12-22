<?php

namespace AppBundle\Service;

use AppBundle\Domain\Task\Command\Cancel;
use AppBundle\Domain\Task\Command\DeleteGroup;
use AppBundle\Domain\Task\Command\MarkAsDone;
use AppBundle\Domain\Task\Command\MarkAsFailed;
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

    public function markAsDone(Task $task, $notes = null)
    {
        $this->commandBus->handle(new MarkAsDone($task, $notes));
    }

    public function markAsFailed(Task $task, $notes = null)
    {
        $this->commandBus->handle(new MarkAsFailed($task, $notes));
    }
}
