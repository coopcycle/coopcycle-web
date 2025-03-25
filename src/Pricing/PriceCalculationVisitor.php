<?php

namespace AppBundle\Pricing;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Delivery\Order;
use AppBundle\Entity\Delivery\OrderItem;
use AppBundle\Entity\Delivery\PricingRule;
use AppBundle\Entity\Delivery\PricingRuleSet;
use AppBundle\Entity\Delivery\ProductVariant;
use AppBundle\Entity\Task;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

// a simplified version of Sylius OrderProcessor structure
// migrate to Sylius later on?
class PriceCalculationVisitor
{
    private array $matchedRules = [];
    private ?Order $order = null;

    public function __construct(
        private PricingRuleSet $ruleSet,
        private ExpressionLanguage $expressionLanguage,
        private LoggerInterface $logger = new NullLogger()
    )
    {
    }

    public function visit(Delivery $delivery): void
    {
        $this->matchedRules = [];
        $taskProductVariants = [];
        $deliveryProductVariant = null;
        $this->order = null;

        $tasks = $delivery->getTasks();

        // Apply the rules to each task/point
        foreach ($tasks as $task) {
            $result = $this->visitTask($this->ruleSet, $delivery, $task);
            $matchedRulesPerTask = $result['matchedRules'];
            if (count($matchedRulesPerTask) > 0) {
                $this->matchedRules = array_merge($this->matchedRules, $matchedRulesPerTask);
                $taskProductVariants[] = $result['productVariant'];
            }
        }

        // Apply the rules to the whole delivery/order
        $result = $this->visitDelivery($this->ruleSet, $delivery);
        $matchedRulesPerDelivery = $result['matchedRules'];
        if (count($matchedRulesPerDelivery) > 0) {
            $this->matchedRules = array_merge($this->matchedRules, $matchedRulesPerDelivery);
            $deliveryProductVariant = $result['productVariant'];
        }


        if (count($this->matchedRules) === 0) {
            $this->logger->info(sprintf('No rule matched'), [
                'strategy' => $this->ruleSet->getStrategy(),
            ]);
        } else {
            $this->order = $this->process($taskProductVariants, $deliveryProductVariant);

            $this->logger->info(sprintf('Calculated price: %d', $this->getPrice()), [
                'strategy' => $this->ruleSet->getStrategy(),
            ]);
        }
    }

    public function getPrice(): ?int
    {
        return $this->order->getItemsTotal();
    }

    public function getOrder(): ?Order
    {
        return $this->order;
    }

    public function getMatchedRules(): array
    {
        return $this->matchedRules;
    }

    private function visitDelivery(PricingRuleSet $ruleSet, Delivery $delivery)
    {
        $matchedRules = [];
        $productOptions = [];

        $deliveryAsExpressionLanguageValues = Delivery::toExpressionLanguageValues($delivery);

        if ($ruleSet->getStrategy() === 'find') {
            foreach ($ruleSet->getRules() as $rule) {
                // LEGACY_TARGET_DYNAMIC is used for backward compatibility
                // for more info see PricingRule::LEGACY_TARGET_DYNAMIC
                if ($rule->getTarget() === PricingRule::TARGET_DELIVERY || $rule->getTarget() === PricingRule::LEGACY_TARGET_DYNAMIC) {
                    if ($rule->matches($deliveryAsExpressionLanguageValues, $this->expressionLanguage)) {
                        $this->logger->info(sprintf('Matched rule "%s"', $rule->getExpression()), [
                            'strategy' => $ruleSet->getStrategy(),
                            'target' => $rule->getTarget(),
                        ]);

                        $matchedRules[] = $rule;
                        $productOptions[] = $rule->apply($deliveryAsExpressionLanguageValues, $this->expressionLanguage);

                        return [
                            'matchedRules' => $matchedRules,
                            'productVariant' => new ProductVariant($productOptions),
                        ];
                    }
                }
            }
        }

        if ($ruleSet->getStrategy() === 'map') {
            $tasks = $delivery->getTasks();

            foreach ($ruleSet->getRules() as $rule) {
                // LEGACY_TARGET_DYNAMIC is used for backward compatibility
                // for more info see PricingRule::LEGACY_TARGET_DYNAMIC
                if ($rule->getTarget() === PricingRule::TARGET_DELIVERY || (count($tasks) <= 2 && $rule->getTarget() === PricingRule::LEGACY_TARGET_DYNAMIC)) {
                    if ($rule->matches($deliveryAsExpressionLanguageValues, $this->expressionLanguage)) {
                        $this->logger->info(sprintf('Matched rule "%s"', $rule->getExpression()), [
                            'strategy' => $ruleSet->getStrategy(),
                            'target' => $rule->getTarget(),
                        ]);

                        $matchedRules[] = $rule;
                        $productOptions[] = $rule->apply($deliveryAsExpressionLanguageValues, $this->expressionLanguage);
                    }
                }
            }
        }

        return [
            'matchedRules' => $matchedRules,
            'productVariant' => new ProductVariant($productOptions),
        ];
    }

    private function visitTask(PricingRuleSet $ruleSet, Delivery $delivery, Task $task)
    {
        $tasks = $delivery->getTasks();

        $matchedRules = [];
        $productOptions = [];

        $taskAsExpressionLanguageValues = $task->toExpressionLanguageValues();

        if ($ruleSet->getStrategy() === 'find') {
            foreach ($ruleSet->getRules() as $rule) {
                if ($rule->getTarget() === PricingRule::TARGET_TASK) {
                    if ($rule->matches($taskAsExpressionLanguageValues, $this->expressionLanguage)) {
                        $price = $rule->evaluatePrice($taskAsExpressionLanguageValues, $this->expressionLanguage);

                        $this->logger->info(sprintf('Matched rule "%s", price: %d', $rule->getExpression(), $price), [
                            'strategy' => $ruleSet->getStrategy(),
                            'target' => $rule->getTarget(),
                        ]);

                        $matchedRules[] = $rule;
                        $pricePerTask = $price;

                        return [
                            'matchedRules' => $matchedRules,
                            'productVariant' => new ProductVariant($productOptions),
                        ];
                    }
                }
            }
        }

        if ($ruleSet->getStrategy() === 'map') {
            foreach ($ruleSet->getRules() as $rule) {
                // LEGACY_TARGET_DYNAMIC is used for backward compatibility
                // for more info see PricingRule::LEGACY_TARGET_DYNAMIC
                if ($rule->getTarget() === PricingRule::TARGET_TASK || (count($tasks) > 2 && $rule->getTarget() === PricingRule::LEGACY_TARGET_DYNAMIC)) {
                    if ($rule->matches($taskAsExpressionLanguageValues, $this->expressionLanguage)) {
                        $this->logger->info(sprintf('Matched rule "%s"', $rule->getExpression()), [
                            'strategy' => $ruleSet->getStrategy(),
                            'target' => $rule->getTarget(),
                        ]);

                        $matchedRules[] = $rule;
                        $productOptions[] = $rule->apply($taskAsExpressionLanguageValues, $this->expressionLanguage);
                    }
                }
            }
        }

        return [
            'matchedRules' => $matchedRules,
            'productVariant' => new ProductVariant($productOptions),
        ];
    }

    /**
     * @param ProductVariant[] $taskProductVariants
     */
    private function process(array $taskProductVariants, ?ProductVariant $deliveryProductVariant): Order
    {
        $taskItems = [];
        $taskItemsTotal = 0;

        foreach ($taskProductVariants as $productVariant) {
            $this->processProductVariant($productVariant, 0);

            $orderItem = new OrderItem($productVariant);

            // Later on: group the same product variants into one OrderItem
            $orderItem->setTotal($productVariant->getPrice());

            $taskItems[] = $orderItem;
            $taskItemsTotal += $orderItem->getTotal();
        }


        $items = $taskItems;
        $itemsTotal = $taskItemsTotal;

        if ($deliveryProductVariant) {
            $this->processProductVariant($deliveryProductVariant, $taskItemsTotal);

            $orderItem = new OrderItem($deliveryProductVariant);
            $orderItem->setTotal($deliveryProductVariant->getPrice());

            $items[] = $orderItem;
            $itemsTotal += $orderItem->getTotal();
        }

        $order = new Order($items);
        $order->setItemsTotal($itemsTotal);

        return $order;
    }

    private function processProductVariant(ProductVariant $productVariant, int $previousItemsTotal): void
    {
        $subtotal = $previousItemsTotal;

        foreach ($productVariant->getProductOptions() as $productOption) {
            $priceAdditive = $productOption->getPriceAdditive();
            $priceMultiplier = $productOption->getPriceMultiplier();

            $previousSubtotal = $subtotal;

            $subtotal += $priceAdditive;
            $subtotal = (int)ceil($subtotal * ($priceMultiplier / 100 / 100));

            $productOption->setPrice($subtotal - $previousSubtotal);
        }

        $productVariant->setPrice($subtotal - $previousItemsTotal);
    }
}
