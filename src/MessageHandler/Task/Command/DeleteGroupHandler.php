<?php

namespace AppBundle\MessageHandler\Task\Command;

use AppBundle\Entity\Task\Group as TaskGroup;
use AppBundle\Message\Task\Command\Cancel;
use AppBundle\Message\Task\Command\DeleteGroup;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler(bus: 'command.bus')]
class DeleteGroupHandler
{

    public function __construct(private ManagerRegistry $doctrine, private MessageBusInterface $commandBus)
    {}

    public function __invoke(DeleteGroup $command)
    {
        $taskGroup = $command->getTaskGroup();

        $tasksToCancel = [];

        foreach ($taskGroup->getTasks() as $task) {

            $taskGroup->removeTask($task);

            if (!$task->isAssigned()) {
                $tasksToCancel[] = $task;
            }
        }

        // Go through the Cancel command, so that the linked order is cancelled as well
        if (count($tasksToCancel) > 0) {
            $this->commandBus->dispatch(new Cancel($tasksToCancel));
        }

        $this->doctrine
            ->getManagerForClass(TaskGroup::class)
            ->remove($taskGroup);
    }
}
