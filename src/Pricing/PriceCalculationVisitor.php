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
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Serializer\Annotation\Groups;

// a simplified version of Sylius OrderProcessor structure
// migrate to Sylius later on?
class PriceCalculationVisitor
{

    private ?Calculation $calculation = null;
    /**
     * @var PricingRule[] $matchedRules
     */
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
        $this->calculation = null;
        $this->matchedRules = [];

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
        $this->order = null;

        $tasks = $delivery->getTasks();

        // Apply the rules to each task/point
        foreach ($tasks as $task) {
            $resultPerTask = $this->visitTask($this->ruleSet, $delivery, $task);
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
        $resultPerDelivery = $this->visitDelivery($this->ruleSet, $delivery);
        $resultPerDelivery->setDelivery($delivery);

        $resultsPerEntity[] = $resultPerDelivery;

        $matchedRulesPerDelivery = array_filter($resultPerDelivery->ruleResults, function ($item) {
            return $item->matched === true;
        });
        if (count($matchedRulesPerDelivery) > 0) {
            $deliveryProductVariant = $resultPerDelivery->productVariant;
        }

        $this->calculation = new Calculation($this->ruleSet, $resultsPerEntity);

        foreach ($resultsPerEntity as $key => $item) {
            foreach ($item->ruleResults as $position => $ruleResult) {
                if ($ruleResult->matched === true) {
                    $this->matchedRules[] = $ruleResult->rule;
                }
            }
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
        $deliveryAsExpressionLanguageValues = Delivery::toExpressionLanguageValues($delivery);

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
        $taskAsExpressionLanguageValues = $task->toExpressionLanguageValues();

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

class Calculation
{
    public readonly PricingRuleSet $ruleSet;

    /**
     * @var Result[] $resultsPerEntity
     */
    public readonly array $resultsPerEntity;

    /**
     * @param PricingRuleSet $ruleSet
     * @param Result[] $resultsPerEntity
     */
    public function __construct(PricingRuleSet $ruleSet, array $resultsPerEntity)
    {
        $this->ruleSet = $ruleSet;
        $this->resultsPerEntity = $resultsPerEntity;
    }
}

class Result
{
    /**
     * @var RuleResult[]
     */
    public readonly array $ruleResults;
    public readonly ?ProductVariant $productVariant;

    public ?Delivery $delivery = null;
    public ?Task $task = null;

    /**
     * @param RuleResult[] $ruleResults
     * @param ProductVariant|null $productVariant
     */
    public function __construct(
        array $ruleResults,
        ?ProductVariant $productVariant = null)
    {
        $this->ruleResults = $ruleResults;
        $this->productVariant = $productVariant;
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
