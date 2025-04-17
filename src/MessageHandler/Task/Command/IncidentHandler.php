<?php

namespace AppBundle\MessageHandler\Task\Command;

use AppBundle\Domain\Task\Event;
use AppBundle\Message\Task\Command\Incident;
use SimpleBus\Message\Recorder\RecordsMessages;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'commandnew.bus')]
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
        $incident = $command->getIncident();

        $this->eventRecorder->record(new Event\TaskIncidentReported($task, $reason, $notes, $data, $incident));
    }
}
