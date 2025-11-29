<?php

namespace AppBundle\MessageHandler\Task\Command;

use AppBundle\Domain\Task\Event;
use AppBundle\Entity\Task;
use AppBundle\Message\Task\Command\Reschedule;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

#[AsMessageHandler(bus: 'command.bus')]
class RescheduleHandler
{

    public function __construct(
        private EntityManagerInterface $em,
        private MessageBusInterface $eventBus
    )
    { }

    public function __invoke(Reschedule $command)
    {
        $task = $command->getTask();
        $rescheduledAfter = $command->getRescheduleAfter();
        $rescheduledBefore = $command->getRescheduledBefore();

        $event = new Event\TaskRescheduled($task, $rescheduledAfter, $rescheduledBefore);
        $this->eventBus->dispatch(
            (new Envelope($event))->with(new DispatchAfterCurrentBusStamp())
        );

        $task->unassign();
        $this->em->flush();
        $task->setAfter($rescheduledAfter);
        $task->setBefore($rescheduledBefore);
        $task->setMetadata('rescheduled', true);
        $task->setStatus(Task::STATUS_TODO);
    }
}
