<?php

namespace AppBundle\MessageHandler\Task\Command;

use AppBundle\Domain\Task\Event;
use AppBundle\Entity\Task;
use AppBundle\Entity\Task\Group as TaskGroup;
use AppBundle\Message\Task\Command\DeleteGroup;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\DispatchAfterCurrentBusMiddleware;

#[AsMessageHandler(bus: 'command.bus')]
class DeleteGroupHandler
{

    public function __construct(private ManagerRegistry $doctrine, private  MessageBusInterface $eventBus)
    {}

    public function __invoke(DeleteGroup $command)
    {
        $taskGroup = $command->getTaskGroup();

        foreach ($taskGroup->getTasks() as $task) {

            $taskGroup->removeTask($task);

            if (!$task->isAssigned()) {
                // FIXME This duplicates the code to cancel a task
                $event = new Event\TaskCancelled($task);
                $this->eventBus->dispatch(
                    (new Envelope($event))->with(new DispatchAfterCurrentBusMiddleware())
                );
                $task->setStatus(Task::STATUS_CANCELLED);
            }
        }

        $this->doctrine
            ->getManagerForClass(TaskGroup::class)
            ->remove($taskGroup);
    }
}
