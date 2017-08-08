<?php

namespace AppBundle\Entity\Listener;

use AppBundle\Entity\OrderItem;
use Doctrine\ORM\Event\LifecycleEventArgs;

class OrderItemListener
{
    /**
     * @param LifecycleEventArgs $args
     */
    public function prePersist(OrderItem $orderItem, LifecycleEventArgs $args)
    {
        if (null === $orderItem->getName()) {
            $orderItem->setName($orderItem->getMenuItem()->getName());
        }

        if (null === $orderItem->getPrice()) {
            $orderItem->setPrice($orderItem->getMenuItem()->getPrice());
        }
    }
}
