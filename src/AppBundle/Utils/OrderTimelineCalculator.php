<?php

namespace AppBundle\Utils;

use AppBundle\Entity\Sylius\OrderTimeline;
use AppBundle\Service\RoutingInterface;
use AppBundle\Sylius\Order\OrderInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

class OrderTimelineCalculator
{
    private $routing;
    private $preparationTimeCalculator;

    /**
     * @param array config
     */
    public function __construct(
        RoutingInterface $routing,
        PreparationTimeCalculator $preparationTimeCalculator)
    {
        $this->routing = $routing;
        $this->preparationTimeCalculator = $preparationTimeCalculator;
    }

    public function calculate(OrderInterface $order)
    {
        $timeline = new OrderTimeline();

        $dropoffExpectedAt = clone $order->getShippedAt();

        $timeline->setDropoffExpectedAt($dropoffExpectedAt);

        $pickupAddress = $order->getRestaurant()->getAddress();
        $dropoffAddress = $order->getShippingAddress();

        $duration = $this->routing->getDuration(
            $pickupAddress->getGeo(),
            $dropoffAddress->getGeo()
        );

        $pickupExpectedAt = clone $dropoffExpectedAt;
        $pickupExpectedAt->modify(sprintf('-%d seconds', $duration));

        $timeline->setPickupExpectedAt($pickupExpectedAt);

        $preparationTime = $this->preparationTimeCalculator->calculate($order);
        $preparationExpectedAt = clone $pickupExpectedAt;
        $preparationExpectedAt->modify(sprintf('-%s', $preparationTime));

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
