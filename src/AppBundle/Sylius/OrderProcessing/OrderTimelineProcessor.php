<?php

namespace AppBundle\Sylius\OrderProcessing;

use AppBundle\Utils\OrderTimelineCalculator;
use AppBundle\Sylius\Order\OrderInterface;
use Sylius\Component\Order\Model\OrderInterface as BaseOrderInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Webmozart\Assert\Assert;

final class OrderTimelineProcessor implements OrderProcessorInterface
{
    private $calculator;

    public function __construct(OrderTimelineCalculator $calculator)
    {
        $this->calculator = $calculator;
    }

    /**
     * {@inheritdoc}
     */
    public function process(BaseOrderInterface $order): void
    {
        Assert::isInstanceOf($order, OrderInterface::class);

        if (!$order->isFoodtech()) {
            return;
        }

        if (OrderInterface::STATE_CART !== $order->getState()) {
            return;
        }

        $timeline = $this->calculator->calculate($order);

        if (null !== $order->getTimeline()) {
            $order->getTimeline()->setPreparationExpectedAt($timeline->getPreparationExpectedAt());
            $order->getTimeline()->setPickupExpectedAt($timeline->getPickupExpectedAt());
            $order->getTimeline()->setDropoffExpectedAt($timeline->getDropoffExpectedAt());
        } else {
            $order->setTimeline($timeline);
        }
    }
}
