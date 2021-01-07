<?php

namespace AppBundle\Utils;

use AppBundle\DataType\TsRange;
use AppBundle\Entity\Sylius\OrderTimeline;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Utils\DateUtils;
use Carbon\Carbon;

class OrderTimelineCalculator
{
    private $preparationTimeResolver;
    private $pickupTimeResolver;

    /**
     * @param PreparationTimeResolver $preparationTimeResolver
     * @param PickupTimeResolver $pickupTimeResolver
     */
    public function __construct(
        PreparationTimeResolver $preparationTimeResolver,
        PickupTimeResolver $pickupTimeResolver)
    {
        $this->preparationTimeResolver = $preparationTimeResolver;
        $this->pickupTimeResolver = $pickupTimeResolver;
    }

    public function calculate(OrderInterface $order)
    {
        $timeline = new OrderTimeline();

        $dropoff = $order->getShippingTimeRange()->getUpper();

        if (!$order->isTakeaway()) {
            $timeline->setDropoffExpectedAt($dropoff);
        }

        $pickup = $this->pickupTimeResolver->resolve($order, $dropoff);
        $timeline->setPickupExpectedAt($pickup);

        $preparation = $this->preparationTimeResolver->resolve($order, $dropoff);
        $timeline->setPreparationExpectedAt($preparation);

        return $timeline;
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
