<?php

namespace AppBundle\Doctrine\EventSubscriber;

use AppBundle\Entity\Sylius\Order;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;

use function PHPUnit\Framework\isNull;

class OrderSubscriber implements EventSubscriber
{
    public function getSubscribedEvents()
    {
        return array(
            Events::onFlush,
        );
    }

    public function onFlush(OnFlushEventArgs $args)
    {
        $em = $args->getEntityManager();
        $uow = $em->getUnitOfWork();

        $isOrder = function ($entity) {
            return $entity instanceof Order;
        };

        $updatedOrders = array_filter($uow->getScheduledEntityUpdates(), $isOrder);

        foreach ($updatedOrders as $order) {
            $entityChangeSet = $uow->getEntityChangeSet($order);
            [ $oldValue, $newValue ] = $entityChangeSet['number'];
            $delivery = $order->getDelivery();

            if (is_null($oldValue) && !is_null($newValue) && !is_null($delivery)) {
                foreach($delivery->getTasks() as $task) {
                    $task->setMetadata('order_number', $newValue);
                }
            }
        }

        $uow->computeChangeSets();
    }
}
