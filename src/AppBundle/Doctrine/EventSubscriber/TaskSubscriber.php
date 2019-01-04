<?php

namespace AppBundle\Doctrine\EventSubscriber;

use AppBundle\Domain\EventStore;
use AppBundle\Domain\Task\Event\TaskAssigned;
use AppBundle\Domain\Task\Event\TaskCreated;
use AppBundle\Domain\Task\Event\TaskUnassigned;
use AppBundle\Entity\ApiUser;
use AppBundle\Entity\Task;
use AppBundle\Entity\TaskList;
use Doctrine\Common\EventSubscriber;
use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;
use SimpleBus\Message\Bus\MessageBus;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class TaskSubscriber implements EventSubscriber
{
    private $eventBus;
    private $routing;
    private $logger;

    private $taskListCache = [];
    private $createdTasks = [];

    public function __construct(MessageBus $eventBus, EventStore $eventStore, LoggerInterface $logger)
    {
        $this->eventBus = $eventBus;
        $this->eventStore = $eventStore;
        $this->logger = $logger;
    }

    public function getSubscribedEvents()
    {
        return array(
            Events::onFlush,
            Events::postFlush,
        );
    }

    private function debug($message)
    {
        $this->logger->debug(sprintf('TaskSubscriber :: %s', $message));
    }

    private function getTaskList(\DateTime $date, ApiUser $courier, OnFlushEventArgs $args)
    {
        $taskListCacheKey = sprintf('%s-%s', $date->format('Y-m-d'), $courier->getUsername());

        if (!isset($this->taskListCache[$taskListCacheKey])) {

            $this->debug(sprintf('TaskList with date = %s, username = %s not found in cache',
                $date->format('Y-m-d'), $courier->getUsername()));

            $taskListRepository = $args->getEntityManager()->getRepository(TaskList::class);

            $taskList = $taskListRepository->findOneBy([
                'date' => $date,
                'courier' => $courier,
            ]);

            if (!$taskList) {
                $taskList = new TaskList();
                $taskList->setDate($date);
                $taskList->setCourier($courier);

                $this->debug(sprintf('TaskList with date = %s, username = %s does not exist, calling persist()…',
                    $date->format('Y-m-d'), $courier->getUsername()));

                $args->getEntityManager()->persist($taskList);
            }

            $this->taskListCache[$taskListCacheKey] = $taskList;
        }

        return $this->taskListCache[$taskListCacheKey];
    }

    private function assignedToHasChanged(Task $task, OnFlushEventArgs $args)
    {
        $unitOfWork = $args->getEntityManager()->getUnitOfWork();

        $entityChangeSet = $unitOfWork->getEntityChangeSet($task);

        return isset($entityChangeSet['assignedTo']);
    }

    public function onFlush(OnFlushEventArgs $args)
    {
        $em = $args->getEntityManager();
        $uow = $em->getUnitOfWork();

        $isTask = function ($entity) {
            return $entity instanceof Task;
        };

        $tasksToInsert = array_filter($uow->getScheduledEntityInsertions(), $isTask);
        $tasksToUpdate = array_filter($uow->getScheduledEntityUpdates(), $isTask);

        $this->debug(sprintf('Found %d instances of Task scheduled for insert', count($tasksToInsert)));
        $this->debug(sprintf('Found %d instances of Task scheduled for update', count($tasksToUpdate)));

        $this->createdTasks = [];
        foreach ($tasksToInsert as $task) {
            $event = $this->eventStore->createEvent(new TaskCreated($task));
            $task->getEvents()->add($event);
            $this->createdTasks[] = $task;
        }

        if (count($tasksToInsert) > 0) {
            $uow->computeChangeSets();
        }

        $tasks = array_merge($tasksToInsert, $tasksToUpdate);

        foreach ($tasks as $task) {

            if (!$this->assignedToHasChanged($task, $args)) {
                continue;
            }

            $entityChangeSet = $uow->getEntityChangeSet($task);

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

                    $taskList = $this->getTaskList($task->getDoneBefore(), $task->getAssignedCourier(), $args);

                    // This will be called when doing $task->assignTo()
                    // It makes sure linked tasks are assigned as well
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

                        $oldTaskList = $this->getTaskList($task->getDoneBefore(), $oldValue, $args);
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
                    // It will be handled by the same subscriber
                    $this->eventBus->handle(new TaskAssigned($task, $newValue));

                    $uow->computeChangeSets();
                }

            } else {

                // The Task has been unassigned
                if ($oldValue !== null) {

                    $this->debug(sprintf('Task#%d has been unassigned', $task->getId()));

                    $taskList = $this->getTaskList($task->getDoneBefore(), $oldValue, $args);

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
                    // It will be handled by the same subscriber
                    $this->eventBus->handle(new TaskUnassigned($task, $oldValue));

                    $uow->computeChangeSets();
                }
            }
        }
    }

    public function postFlush(PostFlushEventArgs $args)
    {
        $em = $args->getEntityManager();
        $uow = $em->getUnitOfWork();

        foreach ($this->createdTasks as $task) {
            $this->eventBus->handle(new TaskCreated($task));
        }
    }
}
