<?php

namespace AppBundle\Domain\Task\Handler;

use AppBundle\Domain\Task\Command\Update;
use AppBundle\Domain\Task\Event;
use SimpleBus\Message\Recorder\RecordsMessages;

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
