<?php

namespace AppBundle\Domain\Task\Handler;

use AppBundle\Domain\Task\Command\Reschedule;
use AppBundle\Domain\Task\Event;
use AppBundle\Entity\Task;
use SimpleBus\Message\Recorder\RecordsMessages;

class RescheduleHandler
{

    public function __construct(private RecordsMessages $eventRecorder)
    { }

    public function __invoke(Reschedule $command)
    {
        $task = $command->getTask();
        $rescheduledAfter = $command->getRescheduleAfter();
        $rescheduledBefore = $command->getRescheduledBefore();

        $this->eventRecorder->record(new Event\TaskRescheduled($task, $rescheduledAfter, $rescheduledBefore));

        $task->setAfter($rescheduledAfter);
        $task->setBefore($rescheduledBefore);
        $task->unassign();
        $task->setMetadata('rescheduled', true);
        $task->setFailureReason(null);
        $task->setStatus(Task::STATUS_TODO);
    }
}
