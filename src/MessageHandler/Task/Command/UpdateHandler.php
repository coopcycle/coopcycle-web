<?php

namespace AppBundle\MessageHandler\Task\Command;

use AppBundle\Domain\Task\Event;
use AppBundle\Message\Task\Command\Update;
use SimpleBus\Message\Recorder\RecordsMessages;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'commandnew.bus')]
class UpdateHandler
{
    private $eventRecorder;

    public function __construct(RecordsMessages $eventRecorder)
    {
        $this->eventRecorder = $eventRecorder;
    }

    public function __invoke(Update $command)
    {
        $task = $command->getTask();

        $this->eventRecorder->record(new Event\TaskUpdated($task));

    }
}
