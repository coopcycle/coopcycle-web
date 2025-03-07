<?php

namespace AppBundle\Doctrine\EventSubscriber\TaskSubscriber;

use AppBundle\Entity\Task;
use AppBundle\Entity\TaskList;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class TaskListProvider
{
    private $objectManager;
    private $taskListCache = [];

    public function __construct(EntityManagerInterface $objectManager)
    {
        $this->objectManager = $objectManager;
    }

    public function getTaskList(Task $task, UserInterface $courier) : TaskList
    {
        // FIXME
        // 1. if task->assignedOn is set, we have explictly set the assignment date -> good, we get the proper TaskList
        // 2. if not use the doneBefore date as default, but in this case their might be problems with tasks spanning on multiple days
        // @see https://github.com/coopcycle/coopcycle-web/issues/874
        $date = null !== $task->getAssignedOn() ? $task->getAssignedOn() : $task->getDoneBefore();

        return $this->getTaskListForUserAndDate($date, $courier);
    }

    public function getTaskListForUserAndDate(\DateTime $date, UserInterface $courier)
    {
        $taskListRepository = $this->objectManager->getRepository(TaskList::class);
        $taskListCacheKey = sprintf('%s-%s', $date->format('Y-m-d'), $courier->getUsername());

        if (!isset($this->taskListCache[$taskListCacheKey])) {

            $taskList = $taskListRepository->findOneBy([
                'date' => $date,
                'courier' => $courier,
            ]);

            if (!$taskList) {
                $taskList = new TaskList();
                $taskList->setDate($date);
                $taskList->setCourier($courier);

                $this->objectManager->persist($taskList);
            }

            $this->taskListCache[$taskListCacheKey] = $taskList;
        }

        return $this->taskListCache[$taskListCacheKey];
    }
}
