<?php

namespace AppBundle\Domain\Task\Handler;

use AppBundle\Domain\Task\Command\Restore;
use AppBundle\Domain\Task\Event;
use AppBundle\Entity\Task;
use SimpleBus\Message\Recorder\RecordsMessages;

class RestoreHandler
{
    private $eventRecorder;

    public function __construct(RecordsMessages $eventRecorder)
    {
        $this->eventRecorder = $eventRecorder;
    }

    public function __invoke(Restore $command)
    {
        $task = $command->getTask();

        $this->eventRecorder->record(new Event\TaskRestored($task));

        $task->setStatus(Task::STATUS_TODO);
    }
}
