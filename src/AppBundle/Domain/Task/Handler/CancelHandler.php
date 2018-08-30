<?php

namespace AppBundle\Domain\Task\Handler;

use AppBundle\Domain\Task\Command\Cancel;
use AppBundle\Domain\Task\Event;
use AppBundle\Entity\Task;
use SimpleBus\Message\Recorder\RecordsMessages;

class CancelHandler
{
    private $eventRecorder;

    public function __construct(RecordsMessages $eventRecorder)
    {
        $this->eventRecorder = $eventRecorder;
    }

    public function __invoke(Cancel $command)
    {
        $task = $command->getTask();

        // TODO Reorder linked tasks?

        $this->eventRecorder->record(new Event\TaskCancelled($task));

        $task->setStatus(Task::STATUS_CANCELLED);
    }
}
