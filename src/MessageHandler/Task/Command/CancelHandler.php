<?php

namespace AppBundle\MessageHandler\Task\Command;

use AppBundle\Domain\Task\Event;
use AppBundle\Entity\Task;
use AppBundle\Message\Task\Command\Cancel as CommandCancel;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

#[AsMessageHandler(bus: 'commandnew.bus')]
class CancelHandler
{
    public function __construct(private MessageBusInterface $eventBus)
    {}

    public function __invoke(CommandCancel $command)
    {
        $task = $command->getTask();

        // TODO Reorder linked tasks?

        $event = new Event\TaskCancelled($task);
        $this->eventBus->dispatch(
            (new Envelope($event))->with(new DispatchAfterCurrentBusStamp())
        );

        $task->setStatus(Task::STATUS_CANCELLED);
    }
}
