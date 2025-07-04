<?php

namespace AppBundle\Pricing;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Delivery\PricingRule;
use AppBundle\Entity\Delivery\PricingRuleSet;
use AppBundle\Entity\Task;
use AppBundle\ExpressionLanguage\DeliveryExpressionLanguageVisitor;
use AppBundle\ExpressionLanguage\TaskExpressionLanguageVisitor;
use AppBundle\Sylius\Product\ProductOptionValueFactory;
use AppBundle\Sylius\Product\ProductOptionValueInterface;
use AppBundle\Sylius\Product\ProductVariantFactory;
use AppBundle\Sylius\Product\ProductVariantInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

// a simplified version of Sylius OrderProcessor structure
// migrate to Sylius in https://github.com/coopcycle/coopcycle/issues/261
class PriceCalculationVisitor
{

    public function __construct(
        private readonly ExpressionLanguage $expressionLanguage,
        private readonly DeliveryExpressionLanguageVisitor $deliveryExpressionLanguageVisitor,
        private readonly TaskExpressionLanguageVisitor $taskExpressionLanguageVisitor,
        private readonly ProductOptionValueFactory $productOptionValueFactory,
        private readonly ProductVariantFactory $productVariantFactory,
        private LoggerInterface $logger = new NullLogger()
    )
    {
    }

    public function visit(Delivery $delivery, PricingRuleSet $ruleSet): PriceCalculationOutput
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
         * @var ProductVariantInterface[] $taskProductVariants
         */
        $taskProductVariants = [];
        /**
         * @var ProductVariantInterface|null $deliveryProductVariant
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
         * @var ProductVariantInterface[] $productVariants
         */
        $productVariants = [];

        if (count($matchedRules) > 0) {
            $productVariants = $this->process($taskProductVariants, $deliveryProductVariant);
        }

        $output = new PriceCalculationOutput($calculation, $matchedRules, $productVariants);

        if (count($productVariants) === 0) {
            $this->logger->info(sprintf('No rule matched'), [
                'strategy' => $ruleSet->getStrategy(),
            ]);
        } else {
            $this->logger->info(sprintf('Calculated price: %d', $output->getPrice()), [
                'strategy' => $ruleSet->getStrategy(),
            ]);
        }

        return $output;
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
         * @var ProductOptionValueInterface[] $productOptionValues
         */
        $productOptionValues = [];

        foreach ($ruleSet->getRules() as $rule) {
            if ($predicate($rule)) {
                $matched = $rule->matches($expressionLanguageValues, $this->expressionLanguage);

                $ruleResults[$rule->getPosition()] = new RuleResult($rule, $matched);

                if ($matched) {
                    $this->logger->info(sprintf('Matched rule "%s"', $rule->getExpression()), [
                        'strategy' => $ruleSet->getStrategy(),
                        'target' => $rule->getTarget(),
                    ]);

                    $productOptionValues[] = $this->productOptionValueFactory->createForPricingRule(
                        $rule,
                        $expressionLanguageValues,
                        $this->expressionLanguage
                    );

                    $this->productOptionValueFactory->createForPricingRule(
                        $rule,
                        $expressionLanguageValues,
                        $this->expressionLanguage
                    );

                    $productVariant = $this->productVariantFactory->createWithProductOptions($productOptionValues, $ruleSet);

                    return new Result($ruleResults, $productVariant);
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
         * @var ProductOptionValueInterface[] $productOptionValues
         */
        $productOptionValues = [];

        foreach ($ruleSet->getRules() as $rule) {
            if ($predicate($rule)) {
                $matched = $rule->matches($expressionLanguageValues, $this->expressionLanguage);

                $ruleResults[$rule->getPosition()] = new RuleResult($rule, $matched);

                if ($matched) {
                    $this->logger->info(sprintf('Matched rule "%s"', $rule->getExpression()), [
                        'strategy' => $ruleSet->getStrategy(),
                        'target' => $rule->getTarget(),
                    ]);

                    $productOptionValues[] = $this->productOptionValueFactory->createForPricingRule(
                        $rule,
                        $expressionLanguageValues,
                        $this->expressionLanguage
                    );
                }
            }
        }

        $matchedRules = array_filter($ruleResults, function ($item) {
            return $item->matched === true;
        });

        if (count($matchedRules) > 0) {
            $productVariant = $this->productVariantFactory->createWithProductOptions($productOptionValues, $ruleSet);

            return new Result($ruleResults, $productVariant);
        } else {
            return new Result($ruleResults);
        }
    }

    /**
     * @param ProductVariantInterface[] $taskProductVariants
     * @return ProductVariantInterface[]
     */
    private function process(array $taskProductVariants, ?ProductVariantInterface $deliveryProductVariant): array
    {
        $taskItemsTotal = 0;

        foreach ($taskProductVariants as $productVariant) {
            $this->processProductVariant($productVariant, 0);

            $taskItemsTotal += $productVariant->getPrice();
        }

        if ($deliveryProductVariant) {
            $this->processProductVariant($deliveryProductVariant, $taskItemsTotal);
        }

        return array_merge($taskProductVariants, $deliveryProductVariant ? [$deliveryProductVariant] : []);
    }

    private function processProductVariant(ProductVariantInterface $productVariant, int $previousItemsTotal): void
    {
        $subtotal = $previousItemsTotal;

        /**
         * @var ProductOptionValueInterface $productOptionValue
         */
        foreach ($productVariant->getOptionValues() as $productOptionValue) {
            if (str_contains($productOptionValue->getValue(), 'price_percentage')) {
                // update the price of the option value, because with percentage-based rules,
                // the price is calculated on the subtotal of the previous steps

                $priceMultiplier = $productOptionValue->getPrice();

                $previousSubtotal = $subtotal;

                $subtotal = (int)ceil($subtotal * ($priceMultiplier / 100 / 100));

                $productOptionValue->setPrice($subtotal - $previousSubtotal);

            } else {
               $subtotal += $productOptionValue->getPrice();
            }
        }

        $productVariant->setPrice($subtotal - $previousItemsTotal);
    }
}
