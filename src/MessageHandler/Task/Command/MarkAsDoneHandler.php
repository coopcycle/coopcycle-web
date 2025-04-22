<?php

namespace AppBundle\MessageHandler\Task\Command;

use AppBundle\Domain\Task\Event;
use AppBundle\Entity\Task;
use AppBundle\Exception\PreviousTaskNotCompletedException;
use AppBundle\Exception\TaskAlreadyCompletedException;
use AppBundle\Exception\TaskCancelledException;
use AppBundle\Integration\Standtrack\StandtrackClient;
use AppBundle\Message\CalculateTaskDistance;
use AppBundle\Message\Task\Command\MarkAsDone;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsMessageHandler(bus: 'commandnew.bus')]
class MarkAsDoneHandler
{

    public function __construct(
        private TranslatorInterface $translator,
        private LoggerInterface $logger,
        private StandtrackClient $standtrackClient,
        private MessageBusInterface $eventBus
    )
    {}

    public function __invoke(MarkAsDone $command)
    {
        /** @var Task $task */
        $task = $command->getTask();

        $event = new Event\TaskDone($task, $command->getNotes());
        $this->eventBus->dispatch(
            (new Envelope($event))->with(new DispatchAfterCurrentBusStamp())
        );

        //TODO: Make this async
        if (!empty($task->getIUB())) {
            try {
                $this->standtrackClient->markDelivered($task->getBarcode(), $task->getIUB());
            } catch (\Exception $e) {
                $this->logger->error(sprintf('Failed to mark task[id=%d] as delivered on Standtrack: %s', $task->getId(), $e->getMessage()));
            }
        }

        $task->setStatus(Task::STATUS_DONE);

        $contactName = $command->getContactName();
        if (!empty($contactName)) {
            $task->getAddress()->setContactName($contactName);
        }
        
        $this->eventBus->dispatch(new CalculateTaskDistance($task->getId()));
    }
}
