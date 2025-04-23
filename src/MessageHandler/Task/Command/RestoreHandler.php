<?php

namespace AppBundle\MessageHandler\Task\Command;

use AppBundle\Domain\Task\Event;
use AppBundle\Entity\Task;
use AppBundle\Message\Task\Command\Restore;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

#[AsMessageHandler(bus: 'command.bus')]
class RestoreHandler
{

    public function __construct(private MessageBusInterface $eventBus)
    {}

    public function __invoke(Restore $command)
    {
        $task = $command->getTask();

        $event = new Event\TaskRestored($task);
        $this->eventBus->dispatch(
            (new Envelope($event))->with(new DispatchAfterCurrentBusStamp())
        );

        $task->setStatus(Task::STATUS_TODO);
    }
}
