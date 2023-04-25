<?php

namespace AppBundle\Domain\Order\Reactor;

use AppBundle\Domain\Order\Event\OrderCancelled;
use AppBundle\Domain\Order\Event\OrderRefused;
use AppBundle\Service\TaskManager;

class CancelTasks
{
    private $taskManager;

    public function __construct(TaskManager $taskManager)
    {
        $this->taskManager = $taskManager;
    }

    public function __invoke(OrderCancelled|OrderRefused $event)
    {
        $order = $event->getOrder();

        if (null === $order->getDelivery()) {
            return;
        }

        foreach ($order->getDelivery()->getTasks() as $task) {
            $this->taskManager->cancel($task);
        }
    }
}
