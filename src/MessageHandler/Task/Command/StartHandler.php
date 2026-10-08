<?php

namespace AppBundle\MessageHandler\Task\Command;

use AppBundle\Domain\Task\Event;
use AppBundle\Entity\Task;
use AppBundle\Message\Task\Command\Start;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;
use Symfony\Component\Workflow\WorkflowInterface;

#[AsMessageHandler(bus: 'command.bus')]
class StartHandler
{
    public function __construct(
        private readonly MessageBusInterface $eventBus,
        private readonly WorkflowInterface $taskStateMachine
    )
    { }

    public function __invoke(Start $command): void
    {
        /** @var Task $task */
        $task = $command->getTask();

        if ($this->taskStateMachine->can($task, 'start')) {
            $this->taskStateMachine->apply($task, 'start');
            $event = new Event\TaskStarted($task);
            $this->eventBus->dispatch(
                (new Envelope($event))->with(new DispatchAfterCurrentBusStamp())
            );
        }
    }
}
