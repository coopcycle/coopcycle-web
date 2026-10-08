<?php

namespace AppBundle\MessageHandler\Task\Command;

use AppBundle\Domain\Task\Event;
use AppBundle\Entity\Task;
use AppBundle\Exception\PreviousTaskNotCompletedException;
use AppBundle\Exception\TaskAlreadyCompletedException;
use AppBundle\Exception\TaskCancelledException;
use AppBundle\Message\Task\Command\MarkAsDone;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsMessageHandler(bus: 'command.bus')]
class MarkAsDoneHandler
{

    public function __construct(
        private MessageBusInterface $eventBus,
        private TranslatorInterface $translator
    )
    {}

    public function __invoke(
        MarkAsDone $command,
    )
    {
        /** @var Task $task */
        $task = $command->getTask();

        // TODO Use StateMachine?
        if ($task->isCompleted()) {
            throw new TaskAlreadyCompletedException(sprintf('Task #%d is already completed', $task->getId()));
        }

        if ($task->isCancelled()) {
            throw new TaskCancelledException(sprintf('Task #%d is cancelled', $task->getId()));
        }

        if ($task->hasPrevious() && !$task->getPrevious()->isCompleted()) {
            // TODO : should be translated client side
            throw new PreviousTaskNotCompletedException(
                $this->translator->trans('tasks.mark_as_done.has_previous', [
                    '%failed_task%' => $task->getId(),
                    '%previous_task%' => $task->getPrevious()->getId(),
                ])
            );
        }

        $event = new Event\TaskDone($task, $command->getNotes());
        $this->eventBus->dispatch(
            (new Envelope($event))->with(new DispatchAfterCurrentBusStamp())
        );

        $task->setStatus(Task::STATUS_DONE);

        $contactName = $command->getContactName();
        if (!empty($contactName)) {
            $task->getAddress()->setContactName($contactName);
        }
    }
}
