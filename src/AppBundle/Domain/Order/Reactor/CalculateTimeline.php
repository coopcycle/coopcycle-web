<?php

namespace AppBundle\Domain\Order\Reactor;

use AppBundle\Domain\Order\Event;
use AppBundle\Utils\OrderTimelineCalculator;
use SimpleBus\Message\Bus\MessageBus;
use Sylius\Component\Payment\Model\PaymentInterface;

class CalculateTimeline
{
    private $calculator;

    public function __construct(OrderTimelineCalculator $calculator)
    {
        $this->calculator = $calculator;
    }

    public function __invoke(Event $event)
    {
        $order = $event->getOrder();

        if (!$order->isFoodtech()) {
            return;
        }

        if ($event instanceof Event\OrderCreated) {
            $timeline = $this->calculator->calculate($order);
            $order->setTimeline($timeline);
        }

        if ($event instanceof Event\OrderDelayed) {
            $this->calculator->delay($order, $event->getDelay());
        }

        if ($event instanceof Event\OrderPicked) {
            // TODO Resolve the date from the event
            $order->getTimeline()->setPickupAt(new \DateTime());
        }

        if ($event instanceof Event\OrderDropped) {
            // TODO Resolve the date from the event
            $order->getTimeline()->setDropoffAt(new \DateTime());
        }
    }
}
