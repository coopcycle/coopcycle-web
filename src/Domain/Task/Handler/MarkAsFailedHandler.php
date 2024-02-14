<?php

namespace AppBundle\Domain\Task\Handler;

use AppBundle\Domain\Task\Command\MarkAsFailed;
use AppBundle\Domain\Task\Event;
use AppBundle\Entity\Task;
use AppBundle\Exception\PreviousTaskNotCompletedException;
use AppBundle\Exception\TaskAlreadyCompletedException;
use AppBundle\Exception\TaskCancelledException;
use SimpleBus\Message\Recorder\RecordsMessages;

class MarkAsFailedHandler
{
    private $eventRecorder;

    public function __construct(RecordsMessages $eventRecorder)
    {
        $this->eventRecorder = $eventRecorder;
    }

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
            $this->eventRecorder->record(new Event\TaskIncidentReported($task, $command->getReason(), $command->getNotes()));
            $task->setHasIncidents(true);
        }

        $this->eventRecorder->record(new Event\TaskFailed($task, $command->getNotes(), $command->getReason()));

        $task->setStatus(Task::STATUS_FAILED);

        $contactName = $command->getContactName();
        if (!empty($contactName)) {
            $task->getAddress()->setContactName($contactName);
        }

   }
}
