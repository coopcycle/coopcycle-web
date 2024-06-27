<?php

namespace AppBundle\Service;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Delivery\PricingRule;
use AppBundle\Entity\Delivery\PricingRuleSet;
use AppBundle\Entity\Task;
use AppBundle\Exception\ShippingAddressMissingException;
use AppBundle\Exception\NoAvailableTimeSlotException;
use AppBundle\Pricing\PriceCalculationVisitor;
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
        $visitor = new PriceCalculationVisitor($ruleSet, $this->expressionLanguage, $this->logger);
        $visitor->visitDelivery($delivery);

        return $visitor->getPrice();
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

        // If no pickup is specified, estimates pickup time from dropoff address and distance
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
