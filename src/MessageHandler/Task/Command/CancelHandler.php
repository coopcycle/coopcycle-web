<?php

namespace AppBundle\MessageHandler\Task\Command;

use AppBundle\Domain\Task\Event;
use AppBundle\Entity\Task;
use AppBundle\Message\Task\Command\Cancel as CommandCancel;
use SimpleBus\Message\Recorder\RecordsMessages;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'commandnew.bus')]
class CancelHandler
{
    private $eventRecorder;

    public function __construct(RecordsMessages $eventRecorder)
    {
        $this->eventRecorder = $eventRecorder;
    }

    public function __invoke(CommandCancel $command)
    {
        $task = $command->getTask();

        // TODO Reorder linked tasks?

        $this->eventRecorder->record(new Event\TaskCancelled($task));

        $task->setStatus(Task::STATUS_CANCELLED);
    }
}
