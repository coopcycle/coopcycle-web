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

                $tasksToAdd = [];
                if ($task->hasPrevious() || $task->hasNext()) {
                    if ($task->hasPrevious()) {
                        $tasksToAdd = [ $task->getPrevious(), $task ];
                    }
                    if ($task->hasNext()) {
                        $tasksToAdd = [ $task, $task->getNext() ];
                    }
                } else {
                    $tasksToAdd = [ $task ];
                }

                // WARNING
                // When tasks have been assigned via the web interface
                // $taskList->containsTask($task) will return true,
                // Because $taskList->setTasks() has been used
                if (!$taskList->containsTask($task)) {
                    $this->logger->debug(sprintf('Adding %d tasks to TaskList', count($tasksToAdd)));
                    foreach ($tasksToAdd as $taskToAdd) {
                        $taskList->addTask($taskToAdd);
                    }
                }

                if ($wasAssigned && !$wasAssignedToSameUser) {

                    $this->logger->debug(sprintf('Removing task #%d from previous TaskList', $task->getId()));

                    $oldTaskList = $this->taskListProvider->getTaskList($task, $oldValue);
                    $oldTaskList->removeTask($task, false);

                    if ($task->hasPrevious() || $task->hasNext()) {
                        if ($task->hasPrevious()) {
                            $oldTaskList->removeTask($task->getPrevious(), false);
                        }
                        if ($task->hasNext()) {
                            $oldTaskList->removeTask($task->getNext(), false);
                        }
                    }
                }

                foreach ($tasksToAdd as $taskToAdd) {

                    $event = new TaskAssigned($taskToAdd, $newValue);

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
                    }
                }
            }

        } else {

            if ($oldValue !== null) {

                $this->logger->debug(sprintf('Task#%d has been unassigned', $task->getId()));

                $taskList = $this->taskListProvider->getTaskList($task, $oldValue);

                $tasksToRemove = [ $task ];

                if ($task->hasPrevious() || $task->hasNext()) {
                    if ($task->hasPrevious()) {
                        $tasksToRemove[] = $task->getPrevious();
                    }
                    if ($task->hasNext()) {
                        $tasksToRemove[] = $task->getNext();
                    }
                }

                foreach ($tasksToRemove as $taskToRemove) {

                    $event = new TaskUnassigned($taskToRemove, $oldValue);

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
                        $taskToRemove->unassign();
                        $taskList->removeTask($taskToRemove);
                        $this->logger->debug(sprintf('Recording event for task #%d', $task->getId()));
                        $this->record($event);
                    }
                }
            }
        }
    }
}
