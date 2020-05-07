<?php

namespace AppBundle\Utils;

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

        $shippingTimeRange = $order->getShippingTimeRange();

        $dropoff = Carbon::instance($shippingTimeRange->getLower())
            ->average($shippingTimeRange->getUpper());

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
        $dropoffExpectedAt = clone $timeline->getDropoffExpectedAt();

        $preparationExpectedAt->modify(sprintf('+%d minutes', $delay));
        $pickupExpectedAt->modify(sprintf('+%d minutes', $delay));
        $dropoffExpectedAt->modify(sprintf('+%d minutes', $delay));

        $timeline->setPreparationExpectedAt($preparationExpectedAt);
        $timeline->setPickupExpectedAt($pickupExpectedAt);
        $timeline->setDropoffExpectedAt($dropoffExpectedAt);

        $order->setShippingTimeRange(
            DateUtils::dateTimeToTsRange($dropoffExpectedAt, 5)
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
