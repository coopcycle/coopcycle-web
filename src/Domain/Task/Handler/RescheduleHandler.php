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
        $rescheduleDateTime = $command->getRescheduleDateTime();

        $this->eventRecorder->record(new Event\TaskRescheduled($task, $rescheduleDateTime));

        $task->setDoneBefore($rescheduleDateTime);
        $task->setStatus(Task::STATUS_TODO);
    }
}
