<?php

namespace AppBundle\Domain\Order\Reactor;

use AppBundle\Domain\Order\Event\OrderCancelled;
use AppBundle\Service\TaskManager;

class CancelTasks
{
    private $taskManager;

    public function __construct(TaskManager $taskManager)
    {
        $this->taskManager = $taskManager;
    }

    public function __invoke(OrderCancelled $event)
    {
        $order = $event->getOrder();

        if (null === $order->getDelivery()) {
            return;
        }

        $this->taskManager->cancel($order->getDelivery()->getPickup());
        $this->taskManager->cancel($order->getDelivery()->getDropoff());
    }
}
