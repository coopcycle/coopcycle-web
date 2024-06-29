<?php

namespace AppBundle\Domain\Order\Reactor;

use AppBundle\Domain\Order\Event\OrderPicked;
use AppBundle\Domain\Order\Event\OrderDropped;
use AppBundle\Domain\Order\Event\OrderFulfilled;
use AppBundle\Domain\Task\Event\TaskDone;
use SimpleBus\Message\Bus\MessageBus;
use Sylius\Component\Payment\Model\PaymentInterface;

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

        // FIXME
        // The order should be fulfilled when the last dropoff is completed
        if ($task->isDropoff()) {

            $shouldCompletePayment = true;

            // In case of last-mile orders, the delivery may be completed *BEFORE* the order is paid.
            $payment = $order->getLastPayment(PaymentInterface::STATE_AUTHORIZED);
            if (null === $payment && !$order->hasVendor()) {
                $shouldCompletePayment = false;
            }

            $this->eventBus->handle(new OrderFulfilled($order, $shouldCompletePayment));
        }
    }
}
