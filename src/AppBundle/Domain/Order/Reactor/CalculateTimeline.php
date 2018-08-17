<?php

namespace AppBundle\Domain\Order\Reactor;

use AppBundle\Domain\Order\Event\OrderCreated;
use AppBundle\Service\StripeManager;
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

    public function __invoke(OrderCreated $event)
    {
        $order = $event->getOrder();

        if ($order->isFoodtech()) {
            $timeline = $this->calculator->calculate($order);
            $order->setTimeline($timeline);
        }
    }
}
