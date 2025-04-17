<?php

namespace AppBundle\MessageHandler\Task\Command;

use AppBundle\Domain\Task\Event;
use AppBundle\Entity\Task;
use AppBundle\Integration\Standtrack\StandtrackClient;
use AppBundle\Message\Task\Command\Start;
use Psr\Log\LoggerInterface;
use SimpleBus\Message\Recorder\RecordsMessages;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Workflow\WorkflowInterface;

#[AsMessageHandler(bus: 'commandnew.bus')]
class StartHandler
{
    public function __construct(
        private readonly RecordsMessages $eventRecorder,
        private readonly WorkflowInterface $taskStateMachine,
        private readonly LoggerInterface $logger,
        private readonly StandtrackClient $standtrackClient
    )
    { }

    public function __invoke(Start $command): void
    {
        /** @var Task $task */
        $task = $command->getTask();

        //TODO: Make this async
        if (!empty($task->getIUB())) {
            try {
                $this->standtrackClient->markInDelivery($task->getBarcode(), $task->getIUB());
            } catch (\Exception $e) {
                $this->logger->error(
                    sprintf(
                        'Failed to mark task[id=%d] as in delivery on Standtrack: %s',
                        $task->getId(),
                        $e->getMessage()
                    )
                );
            }
        }

        if ($this->taskStateMachine->can($task, 'start')) {
            $this->taskStateMachine->apply($task, 'start');
            $this->eventRecorder->record(new Event\TaskStarted($task));
        }
    }
}
