<?php

namespace AppBundle\Domain\Task\Handler;

use AppBundle\Domain\Task\Command\Reschedule;
use AppBundle\Domain\Task\Event;
use AppBundle\Entity\Task;
use Doctrine\ORM\EntityManager;
use SimpleBus\Message\Recorder\RecordsMessages;

class RescheduleHandler
{

    public function __construct(
        private EntityManager $doctrine,
        private RecordsMessages $eventRecorder
    )
    { }

    public function __invoke(Reschedule $command)
    {
        $task = $command->getTask();
        $rescheduledAfter = $command->getRescheduleAfter();
        $rescheduledBefore = $command->getRescheduledBefore();

        $this->eventRecorder->record(new Event\TaskRescheduled($task, $rescheduledAfter, $rescheduledBefore));

        $task->unassign();
        $this->doctrine->flush();
        $task->setAfter($rescheduledAfter);
        $task->setBefore($rescheduledBefore);
        $task->setMetadata('rescheduled', true);
        $task->setStatus(Task::STATUS_TODO);
    }
}
