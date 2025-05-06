<?php

namespace AppBundle\Pricing;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Delivery\Order;
use AppBundle\Entity\Delivery\OrderItem;
use AppBundle\Entity\Delivery\PricingRule;
use AppBundle\Entity\Delivery\PricingRuleSet;
use AppBundle\Entity\Delivery\ProductOption;
use AppBundle\Entity\Delivery\ProductVariant;
use AppBundle\Entity\Task;
use AppBundle\ExpressionLanguage\DeliveryExpressionLanguageVisitor;
use AppBundle\ExpressionLanguage\TaskExpressionLanguageVisitor;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Serializer\Annotation\Groups;

// a simplified version of Sylius OrderProcessor structure
// migrate to Sylius in https://github.com/coopcycle/coopcycle/issues/261
class PriceCalculationVisitor
{

    public function __construct(
        private readonly ExpressionLanguage $expressionLanguage,
        private readonly DeliveryExpressionLanguageVisitor $deliveryExpressionLanguageVisitor,
        private readonly TaskExpressionLanguageVisitor $taskExpressionLanguageVisitor,
        private LoggerInterface $logger = new NullLogger()
    )
    {
    }

    public function visit(Delivery $delivery, PricingRuleSet $ruleSet): Output
    {
        /**
         * @var PricingRule[] $matchedRules
         */
        $matchedRules = [];

        /**
         * @var Result[] $resultsPerEntity
         */
        $resultsPerEntity = [];

        /**
         * @var ProductVariant[] $taskProductVariants
         */
        $taskProductVariants = [];
        /**
         * @var ProductVariant|null $deliveryProductVariant
         */
        $deliveryProductVariant = null;

        $tasks = $delivery->getTasks();

        // Apply the rules to each task/point
        foreach ($tasks as $task) {
            $resultPerTask = $this->visitTask($ruleSet, $delivery, $task);
            $resultPerTask->setTask($task);

            $resultsPerEntity[] = $resultPerTask;

            $matchedRules = array_filter($resultPerTask->ruleResults, function ($item) {
                return $item->matched === true;
            });
            if (count($matchedRules) > 0) {
                $taskProductVariants[] = $resultPerTask->productVariant;
            }
        }

        // Apply the rules to the whole delivery/order
        $resultPerDelivery = $this->visitDelivery($ruleSet, $delivery);
        $resultPerDelivery->setDelivery($delivery);

        $resultsPerEntity[] = $resultPerDelivery;

        $matchedRulesPerDelivery = array_filter($resultPerDelivery->ruleResults, function ($item) {
            return $item->matched === true;
        });
        if (count($matchedRulesPerDelivery) > 0) {
            $deliveryProductVariant = $resultPerDelivery->productVariant;
        }

        $calculation = new Calculation($ruleSet, $resultsPerEntity);

        foreach ($resultsPerEntity as $key => $item) {
            foreach ($item->ruleResults as $position => $ruleResult) {
                if ($ruleResult->matched === true) {
                    $matchedRules[] = $ruleResult->rule;
                }
            }
        }

        /**
         * @var Order|null $order
         */
        $order = null;

        if (count($matchedRules) === 0) {
            $this->logger->info(sprintf('No rule matched'), [
                'strategy' => $ruleSet->getStrategy(),
            ]);
        } else {
            $order = $this->process($taskProductVariants, $deliveryProductVariant);

            $this->logger->info(sprintf('Calculated price: %d', $order->getItemsTotal()), [
                'strategy' => $ruleSet->getStrategy(),
            ]);
        }

        return new Output($calculation, $matchedRules, $order);
    }

    public function getPrice(): ?int
    {
        return $this->order?->getItemsTotal();
    }

    public function getOrder(): ?Order
    {
        return $this->order;
    }

    public function getCalculation(): ?Calculation
    {
        return $this->calculation;
    }

    public function getMatchedRules(): array
    {
        return $this->matchedRules;
    }

    private function visitDelivery(PricingRuleSet $ruleSet, Delivery $delivery): Result
    {
        $deliveryAsExpressionLanguageValues = $this->deliveryExpressionLanguageVisitor->toExpressionLanguageValues($delivery);

        if ($ruleSet->getStrategy() === 'find') {
            return $this->applyFindStrategy($ruleSet, $deliveryAsExpressionLanguageValues, function (PricingRule $rule) {
                // LEGACY_TARGET_DYNAMIC is used for backward compatibility
                // for more info see PricingRule::LEGACY_TARGET_DYNAMIC
                return $rule->getTarget() === PricingRule::TARGET_DELIVERY || $rule->getTarget() === PricingRule::LEGACY_TARGET_DYNAMIC;
            });
        }

        if ($ruleSet->getStrategy() === 'map') {
            $tasks = $delivery->getTasks();

            return $this->applyMapStrategy($ruleSet, $deliveryAsExpressionLanguageValues, function (PricingRule $rule) use ($tasks) {
                // LEGACY_TARGET_DYNAMIC is used for backward compatibility
                // for more info see PricingRule::LEGACY_TARGET_DYNAMIC
                return $rule->getTarget() === PricingRule::TARGET_DELIVERY || ($rule->getTarget() === PricingRule::LEGACY_TARGET_DYNAMIC && count($tasks) <= 2);
            });
        }

        return new Result([]);
    }

    private function visitTask(PricingRuleSet $ruleSet, Delivery $delivery, Task $task): Result
    {
        $taskAsExpressionLanguageValues = $this->taskExpressionLanguageVisitor->toExpressionLanguageValues($task);

        if ($ruleSet->getStrategy() === 'find') {
            return $this->applyFindStrategy($ruleSet, $taskAsExpressionLanguageValues, function (PricingRule $rule) {
                return $rule->getTarget() === PricingRule::TARGET_TASK;
            });
        }

        if ($ruleSet->getStrategy() === 'map') {
            $tasks = $delivery->getTasks();

            return $this->applyMapStrategy($ruleSet, $taskAsExpressionLanguageValues, function (PricingRule $rule) use ($tasks) {
                // LEGACY_TARGET_DYNAMIC is used for backward compatibility
                // for more info see PricingRule::LEGACY_TARGET_DYNAMIC
                return $rule->getTarget() === PricingRule::TARGET_TASK || ($rule->getTarget() === PricingRule::LEGACY_TARGET_DYNAMIC && count($tasks) > 2);
            });
        }

        return new Result([]);
    }

    private function applyFindStrategy(PricingRuleSet $ruleSet, array $expressionLanguageValues, $predicate): Result
    {
        /**
         * @var RuleResult[] $ruleResults
         */
        $ruleResults = [];
        /**
         * @var ProductOption[] $productOptions
         */
        $productOptions = [];

        foreach ($ruleSet->getRules() as $rule) {
            if ($predicate($rule)) {
                $matched = $rule->matches($expressionLanguageValues, $this->expressionLanguage);

                $ruleResults[$rule->getPosition()] = new RuleResult($rule, $matched);

                if ($matched) {
                    $this->logger->info(sprintf('Matched rule "%s"', $rule->getExpression()), [
                        'strategy' => $ruleSet->getStrategy(),
                        'target' => $rule->getTarget(),
                    ]);

                    $productOptions[] = $rule->apply($expressionLanguageValues, $this->expressionLanguage);

                    return new Result($ruleResults, new ProductVariant($productOptions));
                }
            }
        }

        return new Result($ruleResults);
    }

    private function applyMapStrategy(PricingRuleSet $ruleSet, array $expressionLanguageValues, $predicate): Result
    {
        /**
         * @var RuleResult[] $ruleResults
         */
        $ruleResults = [];
        /**
         * @var ProductOption[] $productOptions
         */
        $productOptions = [];

        foreach ($ruleSet->getRules() as $rule) {
            if ($predicate($rule)) {
                $matched = $rule->matches($expressionLanguageValues, $this->expressionLanguage);

                $ruleResults[$rule->getPosition()] = new RuleResult($rule, $matched);

                if ($matched) {
                    $this->logger->info(sprintf('Matched rule "%s"', $rule->getExpression()), [
                        'strategy' => $ruleSet->getStrategy(),
                        'target' => $rule->getTarget(),
                    ]);

                    $productOptions[] = $rule->apply($expressionLanguageValues, $this->expressionLanguage);
                }
            }
        }

        $matchedRules = array_filter($ruleResults, function ($item) {
            return $item->matched === true;
        });

        if (count($matchedRules) > 0) {
            return new Result($ruleResults, new ProductVariant($productOptions));
        } else {
            return new Result($ruleResults);
        }
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

class Output
{
    /**
     * @param Calculation|null $calculation
     * @param PricingRule[] $matchedRules
     * @param Order|null $order
     */
    public function __construct(
        public readonly ?Calculation $calculation,
        public readonly array $matchedRules,
        public readonly ?Order $order)
    {
    }

    public function getPrice(): ?int
    {
        return $this->order?->getItemsTotal();
    }
}

class Calculation
{
    /**
     * @param PricingRuleSet $ruleSet
     * @param Result[] $resultsPerEntity
     */
    public function __construct(
        public readonly PricingRuleSet $ruleSet,
        public readonly array $resultsPerEntity)
    {
    }
}

class Result
{
    public ?Delivery $delivery = null;
    public ?Task $task = null;

    /**
     * @param RuleResult[] $ruleResults
     * @param ProductVariant|null $productVariant
     */
    public function __construct(
        public readonly array $ruleResults,
        public readonly ?ProductVariant $productVariant = null)
    {
    }

    public function setDelivery(Delivery $delivery): void
    {
        $this->delivery = $delivery;
    }

    public function setTask(Task $task): void
    {
        $this->task = $task;
    }
}

class RuleResult
{
    public function __construct(
        #[Groups(['pricing_deliveries'])]
        public readonly PricingRule $rule,
        #[Groups(['pricing_deliveries'])]
        public readonly bool $matched
    )
    {
    }
}
