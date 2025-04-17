<?php

namespace AppBundle\MessageHandler\Task\Command;

use AppBundle\Domain\Task\Event;
use AppBundle\Entity\Task;
use AppBundle\Message\Task\Command\Reschedule;
use Doctrine\ORM\EntityManagerInterface;
use SimpleBus\Message\Recorder\RecordsMessages;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'commandnew.bus')]
class RescheduleHandler
{

    public function __construct(
        private EntityManagerInterface $em,
        private RecordsMessages $eventRecorder
    )
    { }

    public function __invoke(Reschedule $command)
    {
        $task = $command->getTask();
        $rescheduledAfter = $command->getRescheduleAfter();
        $rescheduledBefore = $command->getRescheduledBefore();

        $this->eventRecorder->record(new Event\TaskRescheduled($task, $rescheduledAfter, $rescheduledBefore));

        $task->unassign();
        $this->em->flush();
        $task->setAfter($rescheduledAfter);
        $task->setBefore($rescheduledBefore);
        $task->setMetadata('rescheduled', true);
        $task->setStatus(Task::STATUS_TODO);
    }
}
