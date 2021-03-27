<?php

namespace AppBundle\Entity\Listener;

use AppBundle\Entity\Delivery;
use AppBundle\Service\TagManager;
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
            $task->setTags($tags);
        }
    }
}
