<?php

namespace AppBundle\Domain\Task\Handler;

use AppBundle\Domain\Task\Command\Incident;
use AppBundle\Domain\Task\Command\Reschedule;
use AppBundle\Domain\Task\Event;
use AppBundle\Entity\Task;
use SimpleBus\Message\Recorder\RecordsMessages;

class IncidentHandler
{

    public function __construct(private RecordsMessages $eventRecorder)
    { }

    public function __invoke(Incident $command)
    {
        $task = $command->getTask();
        $reason = $command->getReason();
        $notes = $command->getNotes();
        $data = $command->getData();

        $this->eventRecorder->record(new Event\TaskIncidentReported($task, $reason, $notes, $data));
    }
}
