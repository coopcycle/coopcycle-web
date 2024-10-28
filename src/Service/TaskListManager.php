<?php

namespace AppBundle\Service;

use ApiPlatform\Core\Api\IriConverterInterface;
use AppBundle\Entity\Task;
use AppBundle\Entity\TaskList;
use AppBundle\Entity\TaskList\Item;
use AppBundle\Entity\Tour;
use AppBundle\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class TaskListManager {

    public function __construct(
        protected EntityManagerInterface $entityManager,
        protected IriConverterInterface $iriConverter,
        protected LoggerInterface $logger
    ) {}
    
    /*
        Assign items (tours and tasks). Works like a PUT, i.e. remove items non-present in $newItemsIris. 
    */
    public function assign(TaskList $taskList, $newItemsIris) {

        $currentItems =  array_merge(array(), $taskList->getItems()->toArray());
        $currentTasks = array_merge(array(), $taskList->getTasks());

        // items that were removed in $newItems will be removed thanks to orphan removal
        $taskList->clear();

        foreach($newItemsIris as $position => $newItemIri) {
            $this->logger->debug('match new item IRI ' .$newItemIri);

            $existingItem = array_filter(
                $currentItems,
                function (Item $item) use ($newItemIri) {
                    $this->logger->debug('try match with item IRI ' .$newItemIri);
                    return $item->getItemIri($this->iriConverter) === $newItemIri;}
            );
            // update position
            if (count($existingItem) > 0) {
                $this->logger->debug('found match for ' .$newItemIri);
                $existingItem = array_shift($existingItem);
                $existingItem->setPosition($position);
                $taskList->addItem($existingItem);
            // items that were added to the tasklist
            } else {
                $this->logger->debug('not found match for ' .$newItemIri);
                $taskOrTour = $this->iriConverter->getItemFromIri($newItemIri);
                $item = new Item();
                $item->setPosition($position);
                if ($taskOrTour instanceof Tour) {
                    $item->setTour($taskOrTour);
                } else {
                    $item->setTask($taskOrTour);
                }
                $taskList->addItem($item);
            }
        }

        // Update tasks (i.e. CASCADE assignations information on task.assignedTo)
        // we need to iterate over all the tasks so we trigger EntityChangeSetProcessor - it doesn't seem that the more efficient : $qb = $this->entityManager->createQueryBuilder(->update(Task::class, 't') updates the code
        $newTasks = $taskList->getTasks();
        $tasksToRemove = [];
        foreach ($currentTasks as $task) {
            if (!array_search($task, $newTasks)) {
                $tasksToRemove[] = $task;
                $task->unassign();
            }
        }

        foreach ($newTasks as $task) {
            $task->assignTo($taskList->getCourier(), $taskList->getDate());
        }
    }

    public function getTaskListForUser(\DateTime $date, User $user)
    {
        $taskList = $this->entityManager
            ->getRepository(TaskList::class)
            ->findOneBy(['date' => $date, 'courier' => $user]);

        if (null === $taskList) {
            $taskList = new TaskList();
            $taskList->setDate($date);
            $taskList->setCourier($user);
            $this->entityManager->persist($taskList);
            $this->entityManager->flush();
        }

        return $taskList;
    }

}
