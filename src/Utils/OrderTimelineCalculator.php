<?php

namespace AppBundle\Utils;

use AppBundle\DataType\TsRange;
use AppBundle\Entity\Sylius\OrderTimeline;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Utils\DateUtils;

class OrderTimelineCalculator
{
    private $preparationTimeCalculator;
    private $shippingTimeCalculator;

    /**
     * @param PreparationTimeCalculator $preparationTimeCalculator
     * @param ShippingTimeCalculator $shippingTimeCalculator
     */
    public function __construct(
        PreparationTimeCalculator $preparationTimeCalculator,
        ShippingTimeCalculator $shippingTimeCalculator)
    {
        $this->preparationTimeCalculator = $preparationTimeCalculator;
        $this->shippingTimeCalculator = $shippingTimeCalculator;
    }

    public function calculate(OrderInterface $order, ?TsRange $range = null): OrderTimeline
    {
        $preparationTime = $this->preparationTimeCalculator->calculate($order);
        $shippingTime =
            'delivery' === $order->getFulfillmentMethod() ? $this->shippingTimeCalculator->calculate($order) : null;

        return OrderTimeline::create($order, $range ?? $order->getShippingTimeRange(), $preparationTime, $shippingTime);
    }

    public function delay(OrderInterface $order, $delay)
    {
        $timeline = $order->getTimeline();

        $preparationExpectedAt = clone $timeline->getPreparationExpectedAt();
        $pickupExpectedAt = clone $timeline->getPickupExpectedAt();

        $preparationExpectedAt->modify(sprintf('+%d minutes', $delay));
        $pickupExpectedAt->modify(sprintf('+%d minutes', $delay));

        $timeline->setPreparationExpectedAt($preparationExpectedAt);
        $timeline->setPickupExpectedAt($pickupExpectedAt);

        if (null !== $timeline->getDropoffExpectedAt()) {
            $dropoffExpectedAt = clone $timeline->getDropoffExpectedAt();
            $dropoffExpectedAt->modify(sprintf('+%d minutes', $delay));
            $timeline->setDropoffExpectedAt($dropoffExpectedAt);
        }

        $shippingTimeRange = $order->getShippingTimeRange();

        $shippingTimeRangeLower = clone $shippingTimeRange->getLower();
        $shippingTimeRangeUpper = clone $shippingTimeRange->getUpper();

        $shippingTimeRangeLower->modify(sprintf('+%d minutes', $delay));
        $shippingTimeRangeUpper->modify(sprintf('+%d minutes', $delay));

        $order->setShippingTimeRange(
            TsRange::create($shippingTimeRangeLower, $shippingTimeRangeUpper)
        );

        $delivery = $order->getDelivery();
        if (null !== $delivery) {
            foreach ($delivery->getTasks() as $task) {

                $after = clone $task->getAfter();
                $before = clone $task->getBefore();

                $after->modify(sprintf('+%d minutes', $delay));
                $before->modify(sprintf('+%d minutes', $delay));

                $task->setAfter($after);
                $task->setBefore($before);
            }
        }
    }
}
