<?php

namespace AppBundle\Doctrine\EventSubscriber\TaskSubscriber;

use AppBundle\Domain\Task\Event\TaskAssigned;
use AppBundle\Domain\Task\Event\TaskUnassigned;
use AppBundle\Entity\Task;
use AppBundle\Entity\TaskList\Item;
use AppBundle\Entity\Tour;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SimpleBus\Message\Recorder\ContainsRecordedMessages;
use SimpleBus\Message\Recorder\PrivateMessageRecorderCapabilities;

class EntityChangeSetProcessor implements ContainsRecordedMessages
{
    use PrivateMessageRecorderCapabilities;

    private $taskListProvider;
    private $logger;
    private $tourRepository;

    public function __construct(
        TaskListProvider $taskListProvider,
        LoggerInterface $logger = null,
        EntityManagerInterface $entityManager
    )
    {
        $this->taskListProvider = $taskListProvider;
        $this->logger = $logger ? $logger : new NullLogger();
        $this->tourRepository = $entityManager->getRepository(Tour::class);
    }

    public function process(Task $task, array $entityChangeSet)
    {
        $this->logger->debug(sprintf('Began processing Task#%d', $task->getId()));

        if (!isset($entityChangeSet['assignedTo'])) {
            return;
        }

        [ $oldValue, $newValue ] = $entityChangeSet['assignedTo'];

        // task is still assigned
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

                // When tasks have been assigned via the web interface $taskList->containsTask($task) will return true, because we call Action\TaskList\SetItems
                // the smartphone app calls AssignTrait->assign which set assignment on the task but not on the tasklist, so set it here
                // FIXME : the smartphone app should create/set the taskslit on api/task_list/set_items so to avoid this "backward sync" from task to tasklist
                if ($wasAssigned && !$wasAssignedToSameUser) {
                    $this->logger->debug(sprintf('Removing Task#%d from previous TaskList', $task->getId()));

                    $oldTaskList = $this->taskListProvider->getTaskList($task, $oldValue);
                    // FIXME : this prevent us to enforce uniqueness on task_list_item.task_id, because in this case we cannot add and remove the task_list_item pointing to the same task in the same transaction
                    $oldTaskList->removeTask($task);
                }

                // sync $task.assignedTo info TO tasklist (see explanation above)
                // task in a tour does not need to be added to tasklist, the tour itself is in the tasklist
                // FIXME : add check for tour !$this->tourRepository->findOneByTask($task)
                if (!$taskList->containsTask($task)) {
                    $this->logger->debug(sprintf('Adding Task#%d to TaskList', $task->getId()));

                    $item = new Item();
                    $item->setTask($task);
                    $item->setPosition($taskList->getItems()->count());
                    $taskList->addItem($item);
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
                    $this->logger->debug(sprintf('Task#%d has been assigned, emit new event', $task->getId()));
                    $this->record($event);
                } else {
                    $this->logger->debug(sprintf('Assign event for Task#%d already existed', $task->getId()));
                }
            }

        } else if ($oldValue !== null) { // task was assigned but is not anymore

                $this->logger->debug(sprintf('Task#%d has been unassigned', $task->getId()));

                $taskList = $this->taskListProvider->getTaskList($task, $oldValue);

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
                    // sync $task.assignedTo info to tasklist (see explanation above)
                    $task->unassign();
                    $taskList->removeTask($task);
                    $this->logger->debug(sprintf('Recording event for Task#%d', $task->getId()));
                    $this->record($event);
                } else {
                    $this->logger->debug(sprintf('Unassign event for Task#%d already existed', $task->getId()));
                }
            }
        }
}
