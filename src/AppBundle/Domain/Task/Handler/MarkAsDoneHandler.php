<?php

namespace AppBundle\Domain\Task\Handler;

use AppBundle\Domain\Task\Command\MarkAsDone;
use AppBundle\Domain\Task\Event;
use AppBundle\Entity\Task;
use AppBundle\Exception\PreviousTaskNotCompletedException;
use AppBundle\Exception\TaskAlreadyCompletedException;
use AppBundle\Exception\TaskCancelledException;
use SimpleBus\Message\Recorder\RecordsMessages;

class MarkAsDoneHandler
{
    private $eventRecorder;

    public function __construct(RecordsMessages $eventRecorder)
    {
        $this->eventRecorder = $eventRecorder;
    }

    public function __invoke(MarkAsDone $command)
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

        $this->eventRecorder->record(new Event\TaskDone($task, $command->getNotes()));

        $task->setStatus(Task::STATUS_DONE);

        $contactName = $command->getContactName();
        if (!empty($contactName)) {
            $task->getAddress()->setContactName($contactName);
        }
    }
}
