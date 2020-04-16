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

    public function getTaskList(Task $task, UserInterface $courier)
    {
        $taskListRepository = $this->objectManager->getRepository(TaskList::class);

        // FIXME
        // Using $task->getDoneBefore() causes problems with tasks spanning over several days
        // Here it would assign the task to 2 distinct task lists
        // @see https://github.com/coopcycle/coopcycle-web/issues/874
        $date = null !== $task->getAssignedOn() ? $task->getAssignedOn() : $task->getDoneBefore();

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
