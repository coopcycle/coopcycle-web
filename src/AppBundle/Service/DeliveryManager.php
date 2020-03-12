<?php

namespace AppBundle\Service;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Delivery\PricingRuleSet;
use AppBundle\Service\RoutingInterface;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Utils\OrderTimeHelper;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

class DeliveryManager
{
    private $expressionLanguage;
    private $routing;
    private $orderTimeHelper;

    public function __construct(
        ExpressionLanguage $expressionLanguage,
        RoutingInterface $routing,
        OrderTimeHelper $orderTimeHelper)
    {
        $this->expressionLanguage = $expressionLanguage;
        $this->routing = $routing;
        $this->orderTimeHelper = $orderTimeHelper;
    }

    public function getPrice(Delivery $delivery, PricingRuleSet $ruleSet)
    {
        foreach ($ruleSet->getRules() as $rule) {
            if ($rule->matches($delivery, $this->expressionLanguage)) {
                return $rule->evaluatePrice($delivery, $this->expressionLanguage);
            }
        }
    }

    public function createFromOrder(OrderInterface $order)
    {
        if (null === $order->getRestaurant()) {
            throw new \InvalidArgumentException('Order should reference a restaurant');
        }

        $pickupAddress = $order->getRestaurant()->getAddress();
        $dropoffAddress = $order->getShippingAddress();

        $distance = $this->routing->getDistance(
            $pickupAddress->getGeo(),
            $dropoffAddress->getGeo()
        );
        $duration = $this->routing->getDuration(
            $pickupAddress->getGeo(),
            $dropoffAddress->getGeo()
        );

        $dropoffDoneBefore = $order->getShippedAt();
        if (null === $dropoffDoneBefore) {
            $asap = $this->orderTimeHelper->getAsap($order);
            $dropoffDoneBefore = new \DateTime($asap);
        }

        $pickupDoneBefore = clone $dropoffDoneBefore;
        $pickupDoneBefore->modify(sprintf('-%d seconds', $duration));

        $delivery = new Delivery();

        $pickup = $delivery->getPickup();
        $pickup->setAddress($pickupAddress);
        $pickup->setDoneBefore($pickupDoneBefore);

        $dropoff = $delivery->getDropoff();
        $dropoff->setAddress($dropoffAddress);
        $dropoff->setDoneBefore($dropoffDoneBefore);

        $delivery->setDistance($distance);
        $delivery->setDuration($duration);

        return $delivery;
    }
}
