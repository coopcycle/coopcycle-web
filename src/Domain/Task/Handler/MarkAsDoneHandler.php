<?php

namespace AppBundle\Domain\Task\Handler;

use AppBundle\Domain\Task\Command\MarkAsDone;
use AppBundle\Domain\Task\Event;
use AppBundle\Entity\Task;
use AppBundle\Exception\PreviousTaskNotCompletedException;
use AppBundle\Exception\TaskAlreadyCompletedException;
use AppBundle\Exception\TaskCancelledException;
use AppBundle\Integration\Standtrack\StandtrackClient;
use AppBundle\Message\CalculateTaskDistance;
use Psr\Log\LoggerInterface;
use SimpleBus\Message\Recorder\RecordsMessages;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class MarkAsDoneHandler
{

    public function __construct(
        private RecordsMessages $eventRecorder,
        private TranslatorInterface $translator,
        private LoggerInterface $logger,
        private StandtrackClient $standtrackClient,
        private MessageBusInterface $messageBus
    )
    {}

    public function __invoke(MarkAsDone $command)
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
            throw new PreviousTaskNotCompletedException(
                $this->translator->trans('tasks.mark_as_done.has_previous', [
                    '%failed_task%' => $task->getId(),
                    '%previous_task%' => $task->getPrevious()->getId(),
                ])
            );
        }

        $this->eventRecorder->record(new Event\TaskDone($task, $command->getNotes()));

        //TODO: Make this async
        if (!empty($task->getIUB())) {
            try {
                $this->standtrackClient->markDelivered($task->getBarcode(), $task->getIUB());
            } catch (\Exception $e) {
                $this->logger->error(sprintf('Failed to mark task[id=%d] as delivered on Standtrack: %s', $task->getId(), $e->getMessage()));
            }
        }

        $task->setStatus(Task::STATUS_DONE);

        $contactName = $command->getContactName();
        if (!empty($contactName)) {
            $task->getAddress()->setContactName($contactName);
        }
        
        $this->messageBus->dispatch(new CalculateTaskDistance($task->getId()));
    }
}
