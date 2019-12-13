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

    private function debug($message)
    {
        $this->logger->debug(sprintf('EntityChangeSetProcessor :: %s', $message));
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
                $this->debug(sprintf('Task#%d was not assigned previously', $task->getId()));
            }

            if ($wasAssignedToSameUser) {
                $this->debug(sprintf('Task#%d was already assigned to %s', $task->getId(), $oldValue->getUsername()));
            }

            if (!$wasAssigned || !$wasAssignedToSameUser) {

                $taskList = $this->taskListProvider->getTaskList($task, $newValue);

                if (!$taskList->containsTask($task)) {

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

                    $this->debug(sprintf('Adding %d tasks to TaskList', count($tasksToAdd)));

                    foreach ($tasksToAdd as $taskToAdd) {
                        $taskList->addTask($taskToAdd);
                    }
                }

                if ($wasAssigned && !$wasAssignedToSameUser) {

                    $this->debug(sprintf('Removing task #%d from previous TaskList', $task->getId()));

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

                // No need to add an event for linked tasks,
                // Another event will be trigerred
                $this->record(new TaskAssigned($task, $newValue));
            }

        } else {

            if ($oldValue !== null) {

                $this->debug(sprintf('Task#%d has been unassigned', $task->getId()));

                $taskList = $this->taskListProvider->getTaskList($task, $oldValue);

                $taskList->removeTask($task);

                if ($task->hasPrevious() || $task->hasNext()) {
                    if ($task->hasPrevious()) {
                        $task->getPrevious()->unassign();
                        $taskList->removeTask($task->getPrevious());
                    }
                    if ($task->hasNext()) {
                        $task->getNext()->unassign();
                        $taskList->removeTask($task->getNext());
                    }
                }

                // No need to add an event for linked tasks,
                // Another event will be trigerred
                $this->record(new TaskUnassigned($task, $oldValue));
            }
        }
    }
}
