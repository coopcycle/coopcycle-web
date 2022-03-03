<?php

namespace AppBundle\Domain\Task\Handler;

use AppBundle\Domain\Task\Command\Start;
use AppBundle\Domain\Task\Event;
use AppBundle\Entity\Task;
use SimpleBus\Message\Recorder\RecordsMessages;
use Symfony\Component\Workflow\WorkflowInterface;

class StartHandler
{
    private $eventRecorder;
    private $taskStateMachine;

    public function __construct(RecordsMessages $eventRecorder, WorkflowInterface $taskStateMachine)
    {
        $this->eventRecorder = $eventRecorder;
        $this->taskStateMachine = $taskStateMachine;
    }

    public function __invoke(Start $command)
    {
        $task = $command->getTask();

        if ($this->taskStateMachine->can($task, 'start')) {
            $this->taskStateMachine->apply($task, 'start');
            $this->eventRecorder->record(new Event\TaskStarted($task));
        }
    }
}
