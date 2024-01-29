<?php

namespace AppBundle\Domain\Order\Reactor;

use AppBundle\Domain\Order\Event;
use AppBundle\Domain\Task\Event\TaskRescheduled;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Utils\OrderTimelineCalculator;
use SimpleBus\Message\Bus\MessageBus;
use Sylius\Component\Payment\Model\PaymentInterface;

class CalculateTimeline
{
    private $calculator;
    private $eventBus;

    public function __construct(OrderTimelineCalculator $calculator, MessageBus $eventBus)
    {
        $this->calculator = $calculator;
        $this->eventBus = $eventBus;
    }

    private function getTimeline(OrderInterface $order)
    {
        $timeline = $order->getTimeline();

        if (null === $timeline) {
            $timeline = $this->calculator->calculate($order);
            $order->setTimeline($timeline);
        }

        return $timeline;
    }

    public function __invoke(Event $event)
    {
        $order = $event->getOrder();

        if (!$order->hasVendor()) {
            return;
        }

        $timeline = $this->getTimeline($order);

        if ($event instanceof Event\OrderDelayed) {
            $this->calculator->delay($order, $event->getDelay());

            $delivery = $order->getDelivery();
            if (null !== $delivery) {
                foreach ($delivery->getTasks() as $task) {
                    $this->eventBus->handle(
                        new TaskRescheduled($task, $task->getAfter(), $task->getBefore())
                    );
                }
            }
        }
    }
}
