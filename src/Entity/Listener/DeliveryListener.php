<?php

namespace AppBundle\Entity\Listener;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Task;
use Doctrine\Persistence\Event\LifecycleEventArgs;

class DeliveryListener
{
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

        $store = $delivery->getStore();

        if (null === $store) {
            return;
        }

        $tags = $store->getTags();

        foreach ($delivery->getTasks() as $task) {
            $task->addTags($tags);
        }

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
}
