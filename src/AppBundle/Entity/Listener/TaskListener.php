<?php

namespace AppBundle\Entity\Listener;

use AppBundle\Entity\ApiUser;
use AppBundle\Entity\Task;
use AppBundle\Entity\TaskList;
use AppBundle\Event\TaskAssignEvent;
use AppBundle\Event\TaskCreateEvent;
use AppBundle\Event\TaskUnassignEvent;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class TaskListener
{
    private $dispatcher;
    private $logger;

    public function __construct(EventDispatcherInterface $dispatcher, LoggerInterface $logger)
    {
        $this->dispatcher = $dispatcher;
        $this->logger = $logger;
    }

    public function postPersist(Task $task, LifecycleEventArgs $args)
    {
        $this->dispatcher->dispatch(TaskCreateEvent::NAME, new TaskCreateEvent($task));
    }

    private function assignedToHasChanged(Task $task, LifecycleEventArgs $args)
    {
        if ($args instanceof PreUpdateEventArgs) {
            return $args->hasChangedField('assignedTo');
        }

        $unitOfWork = $args->getObjectManager()->getUnitOfWork();

        $entityChangeSet = $unitOfWork->getEntityChangeSet($task);

        return isset($entityChangeSet['assignedTo']);
    }

    private function taskHasBeenAssigned(Task $task, LifecycleEventArgs $args)
    {
        if ($this->assignedToHasChanged($task, $args)) {
            $unitOfWork = $args->getObjectManager()->getUnitOfWork();
            $entityChangeSet = $unitOfWork->getEntityChangeSet($task);
            [ $oldValue, $newValue ] = $entityChangeSet['assignedTo'];

            return $newValue !== null && $newValue !== $oldValue;
        }

        return false;
    }

    private function taskHasBeenUnassigned(Task $task, LifecycleEventArgs $args)
    {
        if ($this->assignedToHasChanged($task, $args)) {
            $unitOfWork = $args->getObjectManager()->getUnitOfWork();
            $entityChangeSet = $unitOfWork->getEntityChangeSet($task);
            [ $oldValue, $newValue ] = $entityChangeSet['assignedTo'];

            return $newValue === null && $oldValue !== null;
        }

        return false;
    }

    private function getTaskList(\DateTime $date, ApiUser $courier, LifecycleEventArgs $args)
    {
        $taskListRepository = $args->getObjectManager()->getRepository(TaskList::class);

        $taskList = $taskListRepository->findOneBy([
            'date' => $date,
            'courier' => $courier,
        ]);

        if (!$taskList) {
            $taskList = new TaskList();
            $taskList->setDate($date);
            $taskList->setCourier($courier);

            $args->getObjectManager()->persist($taskList);
        }

        return $taskList;
    }

    public function preUpdate(Task $task, PreUpdateEventArgs $args)
    {
        if ($this->assignedToHasChanged($task, $args)) {

            $taskRepository = $args->getObjectManager()->getRepository(Task::class);
            $unitOfWork = $args->getObjectManager()->getUnitOfWork();

            if ($this->taskHasBeenUnassigned($task, $args)) {

                $taskList = $this->getTaskList($task->getDoneBefore(), $args->getOldValue('assignedTo'), $args);

                $taskList->removeTask($task);

                foreach ($taskRepository->findLinked($task) as $linkedTask) {
                    $linkedTask->unassign();
                    $taskList->removeTask($task);

                    $unitOfWork->computeChangeSet(
                        $args->getObjectManager()->getClassMetadata(Task::class), $linkedTask);
                }

                $unitOfWork->computeChangeSet(
                    $args->getObjectManager()->getClassMetadata(TaskList::class), $taskList);
            }

            if ($this->taskHasBeenAssigned($task, $args)) {

                $taskList = $this->getTaskList($task->getDoneBefore(), $task->getAssignedCourier(), $args);

                if (!$taskList->containsTask($task)) {

                    $linked = $taskRepository->findLinked($task);
                    $tasksToAdd = array_merge([$task], $linked);

                    usort($tasksToAdd, function (Task $a, Task $b) {
                        if ($a->hasPrevious() && $a->getPrevious() === $b) {
                            return 1;
                        }
                        if ($b->hasPrevious() && $b->getPrevious() === $a) {
                            return -1;
                        }
                        return 0;
                    });

                    foreach ($tasksToAdd as $taskToAdd) {
                        $taskList->addTask($taskToAdd);
                    }

                    $unitOfWork->computeChangeSet(
                        $args->getObjectManager()->getClassMetadata(TaskList::class), $taskList);
                }
            }
        }
    }

    public function postUpdate(Task $task, LifecycleEventArgs $args)
    {
        $em = $args->getObjectManager();
        $uow = $em->getUnitOfWork();

        $entityChangeSet = $uow->getEntityChangeSet($task);

        if (isset($entityChangeSet['assignedTo'])) {

            [ $oldValue, $newValue ] = $entityChangeSet['assignedTo'];

            if ($newValue !== null) {
                $wasAssigned = $oldValue !== null;
                $wasAssignedToSameUser = $wasAssigned && $oldValue === $newValue;
                if (!$wasAssigned) {
                    $this->logger->debug(sprintf('TaskListener :: Task#%d was not assigned previously', $task->getId()));
                }
                if ($wasAssignedToSameUser) {
                    $this->logger->debug(
                        sprintf('TaskListener :: Task#%d was already assigned to %s', $task->getId(), $oldValue->getUsername()));
                }
                if (!$wasAssigned || !$wasAssignedToSameUser) {
                    $this->dispatcher->dispatch(TaskAssignEvent::NAME, new TaskAssignEvent($task, $newValue));
                }
            } else {
                // The Task has been unassigned
                if ($oldValue !== null) {
                    $this->logger->debug(sprintf('TaskListener :: Task#%d has been unassigned', $task->getId()));
                    $this->dispatcher->dispatch(TaskUnassignEvent::NAME, new TaskUnassignEvent($task, $oldValue));
                }
            }
        }
    }
}
