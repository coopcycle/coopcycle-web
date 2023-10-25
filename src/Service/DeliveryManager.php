<?php

namespace AppBundle\Service;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Delivery\PricingRule;
use AppBundle\Entity\Delivery\PricingRuleSet;
use AppBundle\Entity\Task;
use AppBundle\Exception\ShippingAddressMissingException;
use AppBundle\Exception\NoAvailableTimeSlotException;
use AppBundle\Security\TokenStoreExtractor;
use AppBundle\Service\RoutingInterface;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Utils\DateUtils;
use AppBundle\Utils\OrderTimeHelper;
use AppBundle\Utils\OrderTimelineCalculator;
use AppBundle\Utils\PickupTimeResolver;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

class DeliveryManager
{
    private $expressionLanguage;
    private $routing;
    private $orderTimeHelper;
    private $storeExtractor;
    private $orderTimelineCalculator;
    private $logger;

    public function __construct(
        ExpressionLanguage $expressionLanguage,
        RoutingInterface $routing,
        OrderTimeHelper $orderTimeHelper,
        OrderTimelineCalculator $orderTimelineCalculator,
        TokenStoreExtractor $storeExtractor,
        LoggerInterface $logger = null)
    {
        $this->expressionLanguage = $expressionLanguage;
        $this->routing = $routing;
        $this->orderTimeHelper = $orderTimeHelper;
        $this->orderTimelineCalculator = $orderTimelineCalculator;
        $this->storeExtractor = $storeExtractor;
        $this->logger = $logger ?? new NullLogger();
    }

    public function getPrice(Delivery $delivery, PricingRuleSet $ruleSet)
    {
        if ($ruleSet->getStrategy() === 'find') {

            foreach ($ruleSet->getRules() as $rule) {
                if ($rule->matches($delivery, $this->expressionLanguage)) {
                    $this->logger->info(sprintf('Matched rule "%s"', $rule->getExpression()));

                    return $rule->evaluatePrice($delivery, $this->expressionLanguage);
                }
            }

            return null;
        }

        if ($ruleSet->getStrategy() === 'map') {

            $totalPrice = 0;
            $matchedAtLeastOne = false;

            if (count($delivery->getTasks()) > 2 || $ruleSet->hasOption(PricingRuleSet::OPTION_MAP_ALL_TASKS)) {
                foreach ($delivery->getTasks() as $task) {
                    foreach ($ruleSet->getRules() as $rule) {
                        if ($task->matchesPricingRule($rule, $this->expressionLanguage)) {

                            $price = $task->evaluatePrice($rule, $this->expressionLanguage);

                            $this->logger->info(sprintf('Matched rule "%s", adding %d to price', $rule->getExpression(), $price));

                            $totalPrice += $price;

                            $matchedAtLeastOne = true;
                        }
                    }
                }
            } else {
                foreach ($ruleSet->getRules() as $rule) {
                    if ($rule->matches($delivery, $this->expressionLanguage)) {

                        $price = $rule->evaluatePrice($delivery, $this->expressionLanguage);

                        $this->logger->info(sprintf('Matched rule "%s", adding %d to price', $rule->getExpression(), $price));

                        $totalPrice += $price;

                        $matchedAtLeastOne = true;
                    }
                }
            }

            if ($matchedAtLeastOne) {

                return $totalPrice;
            }
        }

        return null;
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

    public function setDefaults(Delivery $delivery)
    {
        $pickup = $delivery->getPickup();
        $dropoff = $delivery->getDropoff();

        if (null === $store = $delivery->getStore()) {
            $store = $this->storeExtractor->extractStore();
        }

        // If no pickup address is specified, use the store address
        if (null === $pickup->getAddress() && null !== $store) {
            $pickup->setAddress($store->getAddress());
        }

        if (null !== $dropoff->getBefore() && null !== $dropoff->getAddress()) {

            foreach ($delivery->getTasksByType(Task::TYPE_PICKUP) as $p) {
                if (null === $p->getBefore() && null !== $p->getAddress()) {

                    $coords = [$p->getAddress()->getGeo(), $dropoff->getAddress()->getGeo()];
                    $duration = $this->routing->getDuration(...$coords);

                    $pickupDoneBefore = clone $dropoff->getDoneBefore();
                    $pickupDoneBefore->modify(sprintf('-%d seconds', $duration));

                    $p->setBefore($pickupDoneBefore);
                }
            }
        }

        $coords = array_map(fn ($task) => $task->getAddress()->getGeo(), $delivery->getTasks());
        $distance = $this->routing->getDistance(...$coords);

        $delivery->setDistance(ceil($distance));
    }
}
