<?php

namespace AppBundle\Utils;

use AppBundle\Entity\Sylius\OrderTimeline;
use AppBundle\Sylius\Order\OrderInterface;

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

    public function calculate(OrderInterface $order)
    {
        $timeline = new OrderTimeline();

        $dropoffExpectedAt = clone $order->getShippedAt();

        $timeline->setDropoffExpectedAt($dropoffExpectedAt);

        $shippingTime = $this->shippingTimeCalculator->calculate($order);

        $pickupExpectedAt = clone $dropoffExpectedAt;
        $pickupExpectedAt->sub(date_interval_create_from_date_string($shippingTime));

        $timeline->setPickupExpectedAt($pickupExpectedAt);

        $preparationTime = $this->preparationTimeCalculator
            ->createForRestaurant($order->getRestaurant())
            ->calculate($order);
        $preparationExpectedAt = clone $pickupExpectedAt;
        $preparationExpectedAt->sub(date_interval_create_from_date_string($preparationTime));

        $timeline->setPreparationExpectedAt($preparationExpectedAt);

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

        $order->setShippedAt($dropoffExpectedAt);

        $delivery = $order->getDelivery();
        if (null !== $delivery) {
            foreach ($delivery->getTasks() as $task) {

                $doneAfter = clone $task->getDoneAfter();
                $doneBefore = clone $task->getDoneBefore();

                $doneAfter->modify(sprintf('+%d minutes', $delay));
                $doneBefore->modify(sprintf('+%d minutes', $delay));

                $task->setDoneAfter($doneAfter);
                $task->setDoneBefore($doneBefore);
            }
        }
    }
}
