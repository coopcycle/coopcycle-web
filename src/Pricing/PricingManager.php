<?php

namespace AppBundle\Pricing;

use AppBundle\DataType\TsRange;
use AppBundle\Entity\Delivery;
use AppBundle\Entity\Delivery\PricingRuleSet;
use AppBundle\Entity\Store;
use AppBundle\Entity\Sylius\ArbitraryPrice;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Sylius\PriceInterface;
use AppBundle\Entity\Sylius\UseArbitraryPrice;
use AppBundle\Entity\Sylius\PricingStrategy;
use AppBundle\Entity\Sylius\UsePricingRules;
use AppBundle\Entity\Task;
use AppBundle\Entity\Task\RecurrenceRule;
use AppBundle\Service\TimeSlotManager;
use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Sylius\Order\OrderItemInterface;
use AppBundle\Sylius\Product\ProductVariantFactory;
use AppBundle\Sylius\Product\ProductVariantInterface;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Recurr\Rule;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Sylius\Component\Order\Modifier\OrderModifierInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * PricingManager is responsible for calculating the price of a "delivery".
 * "Delivery" here includes both delivery of foodtech orders (where price is added as an order adjustment)
 * and Package Delivery/'LastMile' orders (where price is added as an order item).
 *
 * FIXME: Should we move non-price-related methods into the OrderManager or DeliveryOrderManager class?
 */
class PricingManager
{

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly NormalizerInterface $normalizer,
        private readonly TranslatorInterface $translator,
        private readonly FactoryInterface $orderItemFactory,
        private readonly OrderItemQuantityModifierInterface $orderItemQuantityModifier,
        private readonly OrderModifierInterface $orderModifier,
        private readonly ProductVariantFactory $productVariantFactory,
        private readonly TimeSlotManager $timeSlotManager,
        private readonly PriceCalculationVisitor $priceCalculationVisitor,
        private readonly LoggerInterface $logger
    ) {
    }

    public function getPrice(Delivery $delivery, ?PricingRuleSet $ruleSet): ?int
    {
        // if no Pricing Rules are defined, the default rule is to set the price to 0
        if (null === $ruleSet) {
            return 0;
        }

        $output = $this->getPriceCalculation($delivery, $ruleSet);
        // if the Pricing Rules are configured but none of them match, the price is null
        return $output->getPrice();
    }

    public function getPriceCalculation(Delivery $delivery, PricingRuleSet $ruleSet): ?PriceCalculationOutput
    {
        // Store might be null if it's an embedded form
        $store = $delivery->getStore();
        foreach ($delivery->getTasks() as $task) {
            if (null === $task->getTimeSlot() && null !== $store) {
                // Try to find a time slot by range, when a time slot is not set explicitly

                Task::fixTimeWindow($task);
                $range = TsRange::create($task->getAfter(), $task->getBefore());
                $timeSlot = $this->timeSlotManager->findByRange($store, $range);

                if ($timeSlot) {

                    $task->setTimeSlot($timeSlot);

                } else {

                    $this->logger->warning('No time slot choice found: ', [
                        'store' => $store->getId(),
                        'range' => $range,
                    ]);
                    //FIXME: decide if we want to fail the request
//                    throw new InvalidArgumentException('task.timeSlot.notFound');

                }
            }
        }

        return $this->priceCalculationVisitor->visit($delivery, $ruleSet);
    }

    /**
     * @return ProductVariantInterface[]
     */
    public function getPriceWithPricingStrategy(
        Delivery $delivery,
        PricingStrategy $pricingStrategy
    ): array {
        $store = $delivery->getStore();

        if (null === $store) {
            $this->logger->warning('Delivery has no store');

            return [];
        }

        if ($pricingStrategy instanceof UsePricingRules) {
            $pricingRuleSet = $store->getPricingRuleSet();

            // if no Pricing Rules are defined, the default rule is to set the price to 0
            if (null === $pricingRuleSet) {
                return [
                    $this->getCustomProductVariant(
                        $delivery,
                        new ArbitraryPrice(
                            $this->translator->trans('form.delivery.price.missing'),
                            0
                        )
                    ),
                ];
            }

            $output = $this->getPriceCalculation($delivery, $pricingRuleSet);

            if (count($output->productVariants) === 0) {
                $this->logger->warning('Price could not be calculated');

                return [];
            }

            return $output->productVariants;
        } elseif ($pricingStrategy instanceof UseArbitraryPrice) {
            return [
                $this->getCustomProductVariant(
                    $delivery,
                    $pricingStrategy->getArbitraryPrice()
                ),
            ];
        } else {
            $this->logger->warning('Unsupported pricing config');

            return [];
        }
    }

    /**
     * @param ProductVariantInterface[] $productVariants
     */
    public function processDeliveryOrder(OrderInterface $order, array $productVariants): void {
        if ($order->isFoodtech()) {
            $this->logger->info('processDeliveryOrder command should NOT be called on foodtech orders');
            return;
        }

        //remove previously added items
        foreach ($order->getItems() as $item) {
            $this->orderModifier->removeFromOrder($order, $item);
        }

        $items = [];

        foreach ($productVariants as $productVariant) {
            $orderItem = $this->createOrderItem($productVariant);

            $this->orderItemQuantityModifier->modify($orderItem, 1);

            $items[] = $orderItem;
        }

        foreach ($items as $item) {
            $this->orderModifier->addToOrder($order, $item);
        }
    }

    public function duplicateOrder($store, $orderId): OrderDuplicate | null
    {
        $previousOrder = $this->entityManager
            ->getRepository(Order::class)
            ->find($orderId);

        if (null === $previousOrder) {
            return null;
        }

        $previousDelivery = $previousOrder->getDelivery();

        if (null === $previousDelivery) {
            return null;
        }

        if ($store !== $previousDelivery->getStore()) {
            return null;
        }

        // Keep the original objects untouched, creating new ones instead
        $newTasks = array_map(function ($task) {
            return $task->duplicate();
        }, $previousDelivery->getTasks());

        $delivery = Delivery::createWithTasks(...$newTasks);
        $delivery->setStore($store);

        $previousDeliveryPrice = $previousOrder->getDeliveryPrice();

        return new OrderDuplicate(
            $delivery,
            $previousDeliveryPrice instanceof ArbitraryPrice ? $previousDeliveryPrice : null
        );
    }

    public function createRecurrenceRule(Store $store, Delivery $delivery, Rule $rule, PricingStrategy $pricingStrategy): ?RecurrenceRule
    {
        $recurrenceRule = new RecurrenceRule();
        $recurrenceRule->setStore($store);

        $this->setData($recurrenceRule, $delivery, $rule, $pricingStrategy);

        $this->entityManager->persist($recurrenceRule);
        $this->entityManager->flush();

        return $recurrenceRule;
    }

    public function updateRecurrenceRule(RecurrenceRule $recurrenceRule, Delivery $tempDelivery, Rule $rule, PricingStrategy $pricingStrategy): ?RecurrenceRule
    {
        //FIXME; we have to temporary persist the delivery and tasks, because `TaskNormalizer` depends on database ids;
        // we should properly model subscription template to avoid the need for normalization
        $this->persistTempDelivery($tempDelivery);

        $this->setData($recurrenceRule, $tempDelivery, $rule, $pricingStrategy);
        $this->entityManager->flush();

        $this->cleanupTempDelivery($tempDelivery);

        return $recurrenceRule;
    }

    public function cancelRecurrenceRule(RecurrenceRule $recurrenceRule, Delivery $tempDelivery): void
    {
        $this->persistTempDelivery($tempDelivery);

        $this->entityManager->remove($recurrenceRule);
        $this->entityManager->flush();

        $this->cleanupTempDelivery($tempDelivery);
    }

    private function persistTempDelivery(Delivery $tempDelivery): void
    {
        // tempDelivery is added to entity manager by the form
        $tempDelivery->setOrder(null);
        foreach ($tempDelivery->getTasks() as $task) {
            $task->setPrevious(null);
            $task->setNext(null);
        }
        $this->entityManager->flush();
    }

    private function cleanupTempDelivery(Delivery $tempDelivery): void
    {
        foreach ($tempDelivery->getTasks() as $task) {
            $this->entityManager->remove($task);
        }
        $this->entityManager->remove($tempDelivery);
        $this->entityManager->flush();
    }

    private function setData(RecurrenceRule $recurrenceRule, Delivery $delivery, Rule $rule, PricingStrategy $pricingStrategy): void
    {
        $recurrenceRule->setRule($rule);
        $recurrenceRule->setGenerateOrders(true); // make configurable in #4716

        $tasks = $this->normalizer->normalize($delivery->getTasks(), 'jsonld', ['groups' => ['task_create']]);
        $tasks = array_map(function ($task) {
            unset($task['@id']);

            // Keep only the time part of the date in the template
            $dateTimeFields = ['after', 'before', 'doneAfter', 'doneBefore'];
            foreach ($dateTimeFields as $field) {
                if (!isset($task[$field])) {
                    continue;
                }
                $task[$field] = (new DateTime($task[$field]))->format('H:i:s');
            }

            //FIXME: figure out why the weight is float sometimes
            if (isset($task['weight'])) {
                $task['weight'] = (int) $task['weight'];
            }

            // Do not store if it's not set (otherwise it breaks the denormalization)
            if (null === $task['ref']) {
                unset($task['ref']);
            }

            if (isset($task['tags'])) {
                $task['tags'] = array_map(
                    fn($tag) => $tag['slug'],
                    $task['tags']
                );
            }

            return $task;
        }, $tasks);

        $template = [
            '@type' => 'hydra:Collection',
            'hydra:member' => $tasks,
        ];

        if ($pricingStrategy instanceof UseArbitraryPrice) {
            $arbitraryPrice = $pricingStrategy->getArbitraryPrice();
            $arbitraryPriceTemplate = [
                'variantName' => $arbitraryPrice->getVariantName(),
                'variantPrice' => $arbitraryPrice->getValue(),
            ];
            $recurrenceRule->setArbitraryPriceTemplate($arbitraryPriceTemplate);
        } else {
            $recurrenceRule->setArbitraryPriceTemplate(null);
        }

        $recurrenceRule->setTemplate($template);
    }

    private function createOrderItem(ProductVariantInterface $variant): OrderItemInterface
    {
        /** @var OrderItemInterface $orderItem */
        $orderItem = $this->orderItemFactory->createNew();
        $orderItem->setVariant($variant);
        $orderItem->setUnitPrice(0);
        //TODO: do we need this?
//        $orderItem->setImmutable(true);

        return $orderItem;
    }

    public function getCustomProductVariant(Delivery $delivery, PriceInterface $price): ProductVariantInterface {
        return $this->productVariantFactory->createWithPrice($delivery, $price);
    }
}
