<?php

namespace AppBundle\MessageHandler\Task\Command;

use AppBundle\Domain\Task\Event;
use AppBundle\Entity\Task;
use AppBundle\Exception\PreviousTaskNotCompletedException;
use AppBundle\Exception\TaskAlreadyCompletedException;
use AppBundle\Exception\TaskCancelledException;
use AppBundle\Message\Task\Command\MarkAsFailed;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

#[AsMessageHandler(bus: 'commandnew.bus')]
class MarkAsFailedHandler
{
    public function __construct(private MessageBusInterface $eventBus)
    {}

    public function __invoke(MarkAsFailed $command)
    {
        $task = $command->getTask();

        // TODO Use StateMachine?

        if ($task->isCompleted()) {
            throw new TaskAlreadyCompletedException(sprintf('Task #%d is already completed', $task->getId()));
        }

        if ($task->isCancelled()) {
            throw new TaskCancelledException(sprintf('Task #%d is cancelled', $task->getId()));
        }

        if ($task->hasPrevious() && !$task->getPrevious()->isCompleted()) {
            throw new PreviousTaskNotCompletedException('Previous task must be completed first');
        }

        if (!is_null($command->getReason())) {
            $event = new Event\TaskIncidentReported($task, $command->getReason(), $command->getNotes());
            $this->eventBus->dispatch(
                (new Envelope($event))->with(new DispatchAfterCurrentBusStamp())
            );
        }

        $event = new Event\TaskFailed($task, $command->getNotes(), $command->getReason());
        $this->eventBus->dispatch(
            (new Envelope($event))->with(new DispatchAfterCurrentBusStamp())
        );

        $task->setStatus(Task::STATUS_FAILED);

        $contactName = $command->getContactName();
        if (!empty($contactName)) {
            $task->getAddress()->setContactName($contactName);
        }

   }
}
