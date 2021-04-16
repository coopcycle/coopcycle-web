<?php

namespace AppBundle\Entity\Listener;

use AppBundle\Entity\Delivery;
use Doctrine\Persistence\Event\LifecycleEventArgs;

class DeliveryListener
{
    public function prePersist(Delivery $delivery, LifecycleEventArgs $args)
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
