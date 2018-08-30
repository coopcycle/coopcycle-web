<?php

namespace AppBundle\Domain\Task\Handler;

use AppBundle\Domain\Task\Command\DeleteGroup;
use AppBundle\Domain\Task\Event;
use AppBundle\Entity\Task;
use AppBundle\Entity\Task\Group as TaskGroup;
use SimpleBus\Message\Recorder\RecordsMessages;

class DeleteGroupHandler
{
    private $doctrine;
    private $eventRecorder;

    public function __construct($doctrine, RecordsMessages $eventRecorder)
    {
        $this->doctrine = $doctrine;
        $this->eventRecorder = $eventRecorder;
    }

    public function __invoke(DeleteGroup $command)
    {
        $taskGroup = $command->getTaskGroup();

        foreach ($taskGroup->getTasks() as $task) {

            $taskGroup->removeTask($task);

            if (!$task->isAssigned()) {
                // FIXME This duplicates the code to cancel a task
                $this->eventRecorder->record(new Event\TaskCancelled($task));
                $task->setStatus(Task::STATUS_CANCELLED);
            }
        }

        $this->doctrine
            ->getManagerForClass(TaskGroup::class)
            ->remove($taskGroup);
    }
}
