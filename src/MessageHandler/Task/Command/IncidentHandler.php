<?php

namespace AppBundle\MessageHandler\Task\Command;

use AppBundle\Domain\Task\Event;
use AppBundle\Message\Task\Command\Incident;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

#[AsMessageHandler(bus: 'commandnew.bus')]
class IncidentHandler
{

    public function __construct(private MessageBusInterface $eventBus)
    { }

    public function __invoke(Incident $command)
    {
        $task = $command->getTask();
        $reason = $command->getReason();
        $notes = $command->getNotes();
        $data = $command->getData();
        $incident = $command->getIncident();
        
        $event = new Event\TaskIncidentReported($task, $reason, $notes, $data, $incident);
        $this->eventBus->dispatch(
            (new Envelope($event))->with(new DispatchAfterCurrentBusStamp())
        );
    }
}
