<?php

namespace AppBundle\Service;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Delivery\PricingRuleSet;
use AppBundle\Entity\Task;
use AppBundle\Entity\Task\CollectionInterface as TaskCollectionInterface;
use AppBundle\Exception\ShippingAddressMissingException;
use AppBundle\Exception\NoAvailableTimeSlotException;
use AppBundle\Pricing\PriceCalculationVisitor;
use AppBundle\Security\TokenStoreExtractor;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Utils\DateUtils;
use AppBundle\Utils\OrderTimeHelper;
use AppBundle\Utils\OrderTimelineCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class DeliveryManager
{
    public function __construct(
        private readonly DenormalizerInterface $denormalizer,
        private readonly ExpressionLanguage $expressionLanguage,
        private readonly RoutingInterface $routing,
        private readonly OrderTimeHelper $orderTimeHelper,
        private readonly OrderTimelineCalculator $orderTimelineCalculator,
        private readonly TokenStoreExtractor $storeExtractor,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger = new NullLogger()
    )
    {}

    public function getPrice(Delivery $delivery, ?PricingRuleSet $ruleSet): ?int
    {
        // if no Pricing Rules are defined, the default rule is to set the price to 0
        if (null === $ruleSet) {
            return 0;
        }

        $visitor = new PriceCalculationVisitor($ruleSet, $this->expressionLanguage, $this->logger);
        $visitor->visitDelivery($delivery);

        // if the Pricing Rules are configured but none of them matched, the price is null
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
        $store = $delivery->getStore();

        if (null === $store = $delivery->getStore()) {
            $store = $this->storeExtractor->extractStore();
        }

        // If no pickup address is specified, use the store address
        if (null === $pickup->getAddress() && null !== $store) {
            $pickup->setAddress($store->getAddress());
        }

        // If no pickup time is specified, estimates pickup time from dropoff address and distance
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

    public function createTasksFromRecurrenceRule(Task\RecurrenceRule $recurrenceRule, string $startDate, bool $persist = true): array
    {
        $store = $recurrenceRule->getStore();

        $template = $recurrenceRule->getTemplate();
        $tasksTemplates = $template['@type'] === 'hydra:Collection' ?
            $template['hydra:member'] : [ $template ];

        $tasksTemplates = array_map(function ($taskTemplate) use ($startDate) {

            $taskTemplate['after'] = (new \DateTime($startDate . ' ' . $taskTemplate['after']))->format(\DateTime::ATOM);
            $taskTemplate['before'] = (new \DateTime($startDate . ' ' . $taskTemplate['before']))->format(\DateTime::ATOM);

            return $taskTemplate;
        }, $tasksTemplates);


        $tasks = [];
        foreach ($tasksTemplates as $payload) {

            $task = $this->denormalizer->denormalize($payload, Task::class, 'jsonld', [
                'groups' => ['task_create']
            ]);

            $task->setOrganization($store->getOrganization());
            $task->setRecurrenceRule($recurrenceRule);

            if ($persist) {
                $this->entityManager->persist($task);
            }

            $tasks[] = $task;
        }

        return $tasks;
    }

    public function createDeliveryFromRecurrenceRule(Task\RecurrenceRule $recurrenceRule, string $startDate, bool $persist = true): ?Delivery
    {
        $store = $recurrenceRule->getStore();
        $tasks = $this->createTasksFromRecurrenceRule($recurrenceRule, $startDate, $persist);

        $delivery = null;
        if (Delivery::canCreateWithTasks(...$tasks)) {
            $delivery = Delivery::createWithTasks(...$tasks);
            $store->addDelivery($delivery);

            $this->calculateRoute($delivery);

            if ($persist) {
                $this->entityManager->persist($delivery);
            }
        }

        return $delivery;
    }

    public function calculateRoute(TaskCollectionInterface $taskCollection): void
    {
        $coordinates = [];
        foreach ($taskCollection->getTasks() as $task) {
            $coordinates[] = $task->getAddress()->getGeo();
        }

        if (count($coordinates) <= 1) {
            $taskCollection->setDistance(0);
            $taskCollection->setDuration(0);
            $taskCollection->setPolyline('');
        } else {
            $taskCollection->setDistance($this->routing->getDistance(...$coordinates));
            $taskCollection->setDuration($this->routing->getDuration(...$coordinates));
            $taskCollection->setPolyline($this->routing->getPolyline(...$coordinates));
        }
    }
}
