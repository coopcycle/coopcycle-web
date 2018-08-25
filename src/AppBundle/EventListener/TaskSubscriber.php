<?php

namespace AppBundle\EventListener;

use AppBundle\Domain\Task\Event\TaskAssigned;
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

    public function __construct(MessageBus $eventBus, LoggerInterface $logger)
    {
        $this->eventBus = $eventBus;
        $this->logger = $logger;
    }

    public function getSubscribedEvents()
    {
        return array(
            Events::onFlush,
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

    private function sortTasks(&$tasks)
    {
        usort($tasks, function (Task $a, Task $b) {
            if ($a->hasPrevious() && $a->getPrevious() === $b) {
                return 1;
            }
            if ($b->hasPrevious() && $b->getPrevious() === $a) {
                return -1;
            }
            return 0;
        });
    }

    public function onFlush(OnFlushEventArgs $args)
    {
        $em = $args->getEntityManager();
        $uow = $em->getUnitOfWork();

        $tasks = array_filter($uow->getScheduledEntityUpdates(), function ($entity) {
            return $entity instanceof Task;
        });

        if (count($tasks) === 0) {
            return;
        }

        $this->debug(sprintf('Found %d instances of Task scheduled for update', count($tasks)));

        $taskRepository = $em->getRepository(Task::class);

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

                    if (!$taskList->containsTask($task)) {

                        $linked = $taskRepository->findLinked($task);
                        $tasksToAdd = array_merge([$task], $linked);

                        $this->debug(sprintf('Adding %d tasks to TaskList', count($tasksToAdd)));

                        $this->sortTasks($tasksToAdd);
                        foreach ($tasksToAdd as $taskToAdd) {
                            $taskList->addTask($taskToAdd);
                        }

                        $uow->computeChangeSet($em->getClassMetadata(TaskList::class), $taskList);
                    }

                    $this->eventBus->handle(new TaskAssigned($task, $newValue));
                }
            } else {

                // The Task has been unassigned
                if ($oldValue !== null) {

                    $this->debug(sprintf('Task#%d has been unassigned', $task->getId()));

                    $taskList = $this->getTaskList($task->getDoneBefore(), $oldValue, $args);

                    $taskList->removeTask($task);

                    foreach ($taskRepository->findLinked($task) as $linkedTask) {
                        $linkedTask->unassign();
                        $taskList->removeTask($task);

                        $uow->computeChangeSet($em->getClassMetadata(Task::class), $linkedTask);
                    }

                    $uow->computeChangeSet($em->getClassMetadata(TaskList::class), $taskList);

                    $this->eventBus->handle(new TaskUnassigned($task, $oldValue));
                }
            }
        }
    }
}
