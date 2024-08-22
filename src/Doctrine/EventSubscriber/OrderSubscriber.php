<?php

namespace AppBundle\Doctrine\EventSubscriber;

use AppBundle\Entity\Sylius\Order;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use AppBundle\Service\TaskManager;
use SimpleBus\Message\Recorder\RecordsMessages;
use SimpleBus\SymfonyBridge\Bus\CommandBus;

class OrderSubscriber implements EventSubscriber
{
    public function __construct(
        protected RecordsMessages $eventRecorder,
        protected CommandBus $commandBus,
        protected TaskManager $taskManager)
    {}

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

            if (!array_key_exists('number', $entityChangeSet)) {
                continue;
            }

            [ $oldValue, $newValue ] = $entityChangeSet['number'];
            $delivery = $order->getDelivery();

            if (is_null($oldValue) && !is_null($newValue) && !is_null($delivery)) {
                foreach($delivery->getTasks() as $task) {
                    $task->setMetadata('order_number', $newValue);
                    $this->taskManager->update($task);
                }
            }
        }

        $uow->computeChangeSets();
    }
}
