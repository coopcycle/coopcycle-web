<?php

namespace AppBundle\MessageHandler\Task;

use AppBundle\Domain\Order\Event\OrderPicked;
use AppBundle\Domain\Order\Event\OrderDropped;
use AppBundle\Domain\Order\Event\OrderFulfilled;
use AppBundle\Domain\Task\Event\TaskDone;
use Sylius\Component\Payment\Model\PaymentInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Re-triggers an event in the order domain.
 */
#[AsMessageHandler(handles: TaskDone::class)]
 class PickOrDrop
{

    public function __construct(private MessageBusInterface $eventBus)
    {}

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

        $this->eventBus->dispatch($task->isDropoff() ? new OrderDropped($order) : new OrderPicked($order));

        // FIXME
        // The order should be fulfilled when the last dropoff is completed
        if ($task->isDropoff()) {

            $shouldCompletePayment = true;

            // In case of last-mile orders, the delivery may be completed *BEFORE* the order is paid.
            $payment = $order->getLastPayment(PaymentInterface::STATE_AUTHORIZED);
            if (null === $payment && !$order->hasVendor()) {
                $shouldCompletePayment = false;
            }

            $this->eventBus->dispatch(new OrderFulfilled($order, $shouldCompletePayment));
        }
    }
}
