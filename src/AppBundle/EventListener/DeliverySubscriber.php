<?php

namespace AppBundle\EventListener;

use AppBundle\Entity\Delivery;
use Doctrine\Common\EventSubscriber;
use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\Events;

class DeliverySubscriber implements EventSubscriber
{
    public function getSubscribedEvents()
    {
        return array(
            Events::prePersist,
        );
    }

    public function prePersist(LifecycleEventArgs $args)
    {
        $entity = $args->getObject();

        if ($entity instanceof Delivery) {
            foreach (Delivery::createTasks($entity) as $task) {
                $task->setDelivery($entity);
                $entity->addTask($task);
            }
        }
    }
}
