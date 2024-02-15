<?php

namespace AppBundle\Doctrine\EventSubscriber\TaskSubscriber;

use AppBundle\Domain\Task\Event\TaskAssigned;
use AppBundle\Domain\Task\Event\TaskUnassigned;
use AppBundle\Entity\Task;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SimpleBus\Message\Recorder\ContainsRecordedMessages;
use SimpleBus\Message\Recorder\PrivateMessageRecorderCapabilities;

class EntityChangeSetProcessor implements ContainsRecordedMessages
{
    use PrivateMessageRecorderCapabilities;

    private $taskListProvider;
    private $logger;

    public function __construct(TaskListProvider $taskListProvider, LoggerInterface $logger = null)
    {
        $this->taskListProvider = $taskListProvider;
        $this->logger = $logger ? $logger : new NullLogger();
    }

    public function process(Task $task, array $entityChangeSet)
    {
        if (!isset($entityChangeSet['assignedTo'])) {
            return;
        }

        [ $oldValue, $newValue ] = $entityChangeSet['assignedTo'];

        if ($newValue !== null) {

            $wasAssigned = $oldValue !== null;
            $wasAssignedToSameUser = $wasAssigned && $oldValue === $newValue;

            if (!$wasAssigned) {
                $this->logger->debug(sprintf('Task#%d was not assigned previously', $task->getId()));
            }

            if ($wasAssignedToSameUser) {
                $this->logger->debug(sprintf('Task#%d was already assigned to %s', $task->getId(), $oldValue->getUsername()));
            }

            if (!$wasAssigned || !$wasAssignedToSameUser) {

                $taskList = $this->taskListProvider->getTaskList($task, $newValue);

                // WARNING
                // When tasks have been assigned via the web interface
                // $taskList->containsTask($task) will return true,
                // Because $taskList->setTasks() has been used
                if (!$taskList->containsTask($task)) {
                    $this->logger->debug(sprintf('Adding #%d task to TaskList', $task->getId()));
                    $taskList->addTask($task);
                }

                if ($wasAssigned && !$wasAssignedToSameUser) {
                    $this->logger->debug(sprintf('Removing task #%d from previous TaskList', $task->getId()));

                    $oldTaskList = $this->taskListProvider->getTaskList($task, $oldValue);
                    $oldTaskList->removeTask($task, false);
                }

                $event = new TaskAssigned($task, $newValue);

                $exists = false;
                foreach ($this->recordedMessages() as $recordedMessage) {
                    if ($recordedMessage instanceof TaskAssigned) {
                        if ($recordedMessage->getTask() === $event->getTask() && $recordedMessage->getUser() === $event->getUser()) {
                            $exists = true;
                            break;
                        }
                    }
                }

                if (!$exists) {
                    $this->record($event);
                } else {
                    $this->logger->debug(sprintf('Assign event for task #%d already existed', $task->getId()));
                }
            }

        } else {

            if ($oldValue !== null) {

                $this->logger->debug(sprintf('Task#%d has been unassigned', $task->getId()));

                $taskList = $this->taskListProvider->getTaskList($task, $oldValue);

                $tasksToRemove = [ $task ];

                $event = new TaskUnassigned($task, $oldValue);

                $exists = false;
                foreach ($this->recordedMessages() as $recordedMessage) {
                    if ($recordedMessage instanceof TaskUnassigned) {
                        if ($recordedMessage->getTask() === $event->getTask() && $recordedMessage->getUser() === $event->getUser()) {
                            $exists = true;
                            break;
                        }
                    }
                }

                if (!$exists) {
                    $task->unassign();
                    $taskList->removeTask($task);
                    $this->logger->debug(sprintf('Recording event for task #%d', $task->getId()));
                    $this->record($event);
                } else {
                    $this->logger->debug(sprintf('Unassign event for task #%d already existed', $task->getId()));
                }
            }
        }
    }
}
