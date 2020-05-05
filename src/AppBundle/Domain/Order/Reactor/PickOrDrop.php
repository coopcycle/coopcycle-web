<?php

namespace AppBundle\Domain\Order\Reactor;

use AppBundle\Domain\Order\Event\OrderPicked;
use AppBundle\Domain\Order\Event\OrderDropped;
use AppBundle\Domain\Order\Event\OrderFulfilled;
use AppBundle\Domain\Task\Event\TaskDone;
use SimpleBus\Message\Bus\MessageBus;

/**
 * Re-triggers an event in the order domain.
 */
class PickOrDrop
{
    private $eventBus;

    public function __construct(MessageBus $eventBus)
    {
        $this->eventBus = $eventBus;
    }

    public function __invoke(TaskDone $event)
    {
        $task = $event->getTask();

        $delivery = $task->getDelivery();

        if (null === $delivery) {
            return;
        }

        $order = $delivery->getOrder();

        if (null === $order) {
            return;
        }

        $this->eventBus->handle($task->isDropoff() ? new OrderDropped($order) : new OrderPicked($order));

        if ($task->isDropoff()) {
            $this->eventBus->handle(new OrderFulfilled($order));
        }
    }
}
