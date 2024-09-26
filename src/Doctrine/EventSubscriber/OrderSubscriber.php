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
    {
    }

    private array $changedTasks = [];

    public function getSubscribedEvents()
    {
        return array(
            Events::onFlush,
            Events::postFlush,
        );
    }

    /**
     * Save the order number in task.metadata.order_number for non-foodtech orders
     */
    public function onFlush(OnFlushEventArgs $args): void
    {
        $this->changedTasks = [];

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

            // for foodtech orders the delivery is not created when we assign the number, it is created when the order is accepted
            $delivery = $order->getDelivery();

            if (is_null($oldValue) && !is_null($newValue) && !is_null($delivery)) {
                foreach ($delivery->getTasks() as $task) {
                    $task->setMetadata('order_number', $newValue);
                    $uow->recomputeSingleEntityChangeSet($em->getClassMetadata(get_class($task)), $task);

                    $this->changedTasks[] = $task;
                }
            }
        }
    }

    public function postFlush(): void
    {
        foreach ($this->changedTasks as $task) {
            $this->taskManager->update($task);
        }
    }
}
