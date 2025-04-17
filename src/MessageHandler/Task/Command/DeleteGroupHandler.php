<?php

namespace AppBundle\MessageHandler\Task\Command;

use AppBundle\Domain\Task\Event;
use AppBundle\Entity\Task;
use AppBundle\Entity\Task\Group as TaskGroup;
use AppBundle\Message\Task\Command\DeleteGroup;
use Doctrine\Persistence\ManagerRegistry;
use SimpleBus\Message\Recorder\RecordsMessages;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'commandnew.bus')]
class DeleteGroupHandler
{

    public function __construct(private ManagerRegistry $doctrine, private  RecordsMessages $eventRecorder)
    {}

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
