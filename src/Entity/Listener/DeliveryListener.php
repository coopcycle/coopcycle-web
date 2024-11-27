<?php

namespace AppBundle\Entity\Listener;

use AppBundle\Doctrine\EventSubscriber\TaskSubscriber\TaskListProvider;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Task;
use AppBundle\Entity\TaskList\Item;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\Event\LifecycleEventArgs;

class DeliveryListener
{
    public function __construct(
        protected EntityManagerInterface $entityManager,
        protected TaskListProvider $taskListProvider
    )
    {
        
    }
    public function prePersist(Delivery $delivery, LifecycleEventArgs $args)
    {
        $comments = '';
        
        if ($delivery->hasPackages()) {
            foreach ($delivery->getPackages() as $package) {
                $comments .= $package->getQuantity() .' × ' . $package->getPackage()->getName();
                $comments .= "\n";
            }
        }

        $grams = $delivery->getWeight();
        if (null !== $grams) {
            $weight = number_format($grams / 1000, 2) . ' kg';
            $comments .= $weight;
        }

        if (!empty($comments)) {
            $prevComments = $delivery->getPickup()->getComments();

            $delivery->getPickup()->setComments(
                $prevComments ? ($prevComments . "\n\n" . $comments) : $comments
            );
        }

        $this->applyStoreTags($delivery);

        $tasks = $delivery->getTasks();

        if (count($tasks) > 2) {

            $firstTask = array_shift($tasks);
            $dropoffs = array_filter($tasks, fn (Task $t) => $t->getType() === Task::TYPE_DROPOFF);
            $otherTasksAreDropoffs = count($dropoffs) === count($tasks);

            if ($firstTask->isPickup() && $otherTasksAreDropoffs) {
                $firstTask->setNext(null);
                foreach ($dropoffs as $dropoff) {
                    $dropoff->setPrevious($firstTask);
                    $dropoff->setNext(null);
                }
            }
        }
    }

    public function postPersist(Delivery $delivery)
    {
        
        if ($delivery->getStore() && $delivery->getStore()->getDefaultCourier()) {
            $courier = $delivery->getStore()->getDefaultCourier();
            
            foreach ($delivery->getTasks() as $task) {
                $taskList = $this->taskListProvider->getTaskList($task, $courier);
                $taskList->appendTask($task);
                $this->entityManager->persist($taskList);
            }
            
            $this->entityManager->flush();
        }
    }

    private function applyStoreTags(Delivery $delivery)
    {
        $store = $delivery->getStore();

        if (null === $store) {
            return;
        }

        $tags = $store->getTags();

        foreach ($delivery->getTasks() as $task) {
            $task->addTags($tags);
        }
    }
}
