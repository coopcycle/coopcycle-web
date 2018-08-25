<?php

namespace AppBundle\Utils;

use AppBundle\Entity\Sylius\OrderTimeline;
use AppBundle\Service\RoutingInterface;
use AppBundle\Sylius\Order\OrderInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

/**
 * The preparation duration is calculated based on the order total.
 * The $config constructor variable should be an array like this:
 * <pre>
 * [
 *   'total <= 2000'       => '10 minutes',
 *   'total in 2000..5000' => '15 minutes',
 *   'total > 5000'        => '30 minutes',
 * ]
 * </pre>
 */
class OrderTimelineCalculator
{
    private $routing;
    private $config;
    private $language;

    /**
     * @param array config
     */
    public function __construct(RoutingInterface $routing, array $config)
    {
        $this->routing = $routing;
        $this->config = $config;

        $this->language = new ExpressionLanguage();
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

        $preparation = null;
        foreach ($this->config as $expression => $value) {
            $values = [
                'total' => $order->getTotal(),
            ];

            if (true === $this->language->evaluate($expression, $values)) {
                $preparation = $value;
                break;
            }
        }

        $preparationExpectedAt = clone $pickupExpectedAt;
        $preparationExpectedAt->modify(sprintf('-%s', $preparation));

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

        foreach ($order->getDelivery()->getTasks() as $task) {

            $doneAfter = clone $task->getDoneAfter();
            $doneBefore = clone $task->getDoneBefore();

            $doneAfter->modify(sprintf('+%d minutes', $delay));
            $doneBefore->modify(sprintf('+%d minutes', $delay));

            $task->setDoneAfter($doneAfter);
            $task->setDoneBefore($doneBefore);
        }
    }
}
