<?php

namespace AppBundle\MessageHandler\Order;

use AppBundle\Domain\Order\Event;
use AppBundle\Domain\Order\Event\OrderCreated;
use AppBundle\Domain\Order\Event\OrderDelayed;
use AppBundle\Domain\Order\Event\OrderDropped;
use AppBundle\Domain\Order\Event\OrderPicked;
use AppBundle\Domain\Task\Event\TaskRescheduled;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Utils\OrderTimelineCalculator;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler()]
class CalculateTimeline
{
    private $calculator;
    private $eventBus;

    public function __construct(OrderTimelineCalculator $calculator, MessageBusInterface $eventBus)
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

    public function __invoke(OrderCreated|OrderDelayed|OrderPicked|OrderDropped $event)
    {
        $order = $event->getOrder();

        if (!$order->hasVendor()) {
            return;
        }

        $this->getTimeline($order);

        if ($event instanceof Event\OrderDelayed) {
            $this->calculator->delay($order, $event->getDelay());

            $delivery = $order->getDelivery();
            if (null !== $delivery) {
                foreach ($delivery->getTasks() as $task) {
                    $this->eventBus->dispatch(
                        new TaskRescheduled($task, $task->getAfter(), $task->getBefore())
                    );
                }
            }
        }
    }
}
