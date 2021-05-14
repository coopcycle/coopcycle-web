<?php

namespace AppBundle\Service;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Delivery\PricingRule;
use AppBundle\Entity\Delivery\PricingRuleSet;
use AppBundle\Exception\ShippingAddressMissingException;
use AppBundle\Exception\NoAvailableTimeSlotException;
use AppBundle\Service\RoutingInterface;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Utils\DateUtils;
use AppBundle\Utils\OrderTimeHelper;
use AppBundle\Utils\OrderTimelineCalculator;
use AppBundle\Utils\PickupTimeResolver;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

class DeliveryManager
{
    private $expressionLanguage;
    private $routing;
    private $orderTimeHelper;
    private $orderTimelineCalculator;

    public function __construct(
        ExpressionLanguage $expressionLanguage,
        RoutingInterface $routing,
        OrderTimeHelper $orderTimeHelper,
        OrderTimelineCalculator $orderTimelineCalculator)
    {
        $this->expressionLanguage = $expressionLanguage;
        $this->routing = $routing;
        $this->orderTimeHelper = $orderTimeHelper;
        $this->orderTimelineCalculator = $orderTimelineCalculator;
    }

    public function getPrice(Delivery $delivery, PricingRuleSet $ruleSet)
    {
        $this->lastMatchedRule = null;

        if ($ruleSet->getStrategy() === 'find') {

            foreach ($ruleSet->getRules() as $rule) {
                if ($rule->matches($delivery, $this->expressionLanguage)) {
                    $this->lastMatchedRule = $rule;

                    return $rule->evaluatePrice($delivery, $this->expressionLanguage);
                }
            }

            return null;
        }

        if ($ruleSet->getStrategy() === 'map') {

            $totalPrice = 0;
            foreach ($ruleSet->getRules() as $rule) {
                if ($rule->matches($delivery, $this->expressionLanguage)) {
                    $this->lastMatchedRule = $rule;

                    $price = $rule->evaluatePrice($delivery, $this->expressionLanguage);
                    $totalPrice += $price;
                }
            }

            if ($this->lastMatchedRule !== null) {

                return $totalPrice;
            }
        }
    }

    public function getLastMatchedRule(): ?PricingRule
    {
        return $this->lastMatchedRule;
    }

    public function createFromOrder(OrderInterface $order)
    {
        if (!$order->hasVendor()) {
            throw new \InvalidArgumentException('Order should reference a vendor');
        }

        $pickupAddress = $order->getPickupAddress();
        $dropoffAddress = $order->getShippingAddress();

        if (null === $dropoffAddress) {
            throw new ShippingAddressMissingException('Order does not have a shipping address');
        }

        $dropoffTimeRange = $order->getShippingTimeRange();
        if (null === $dropoffTimeRange) {
            $dropoffTimeRange =
                $this->orderTimeHelper->getShippingTimeRange($order);
        }

        if (null === $dropoffTimeRange) {
            throw new NoAvailableTimeSlotException('No time slot is avaible');
        }

        $distance = $this->routing->getDistance(
            $pickupAddress->getGeo(),
            $dropoffAddress->getGeo()
        );
        $duration = $this->routing->getDuration(
            $pickupAddress->getGeo(),
            $dropoffAddress->getGeo()
        );

        $timeline = $this->orderTimelineCalculator->calculate($order, $dropoffTimeRange);
        $pickupTime = $timeline->getPickupExpectedAt();

        $pickupTimeRange = DateUtils::dateTimeToTsRange($pickupTime, 5);

        $delivery = new Delivery();

        $pickup = $delivery->getPickup();
        $pickup->setAddress($pickupAddress);
        $pickup->setAfter($pickupTimeRange->getLower());
        $pickup->setBefore($pickupTimeRange->getUpper());

        $dropoff = $delivery->getDropoff();
        $dropoff->setAddress($dropoffAddress);
        $dropoff->setAfter($dropoffTimeRange->getLower());
        $dropoff->setBefore($dropoffTimeRange->getUpper());

        $delivery->setDistance($distance);
        $delivery->setDuration($duration);

        $delivery->setOrder($order);

        return $delivery;
    }
}
