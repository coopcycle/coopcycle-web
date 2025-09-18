<?php

namespace AppBundle\Pricing;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Delivery\PricingRule;
use AppBundle\Entity\Delivery\PricingRuleSet;
use AppBundle\Entity\Task;
use AppBundle\ExpressionLanguage\DeliveryExpressionLanguageVisitor;
use AppBundle\ExpressionLanguage\TaskExpressionLanguageVisitor;
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
        private readonly ProductVariantFactory $productVariantFactory,
        private readonly ProductVariantNameGenerator $productVariantNameGenerator,
        private readonly OnDemandDeliveryProductProcessor $onDemandDeliveryProductProcessor,
        private readonly LoggerInterface $feeCalculationLogger = new NullLogger()
    )
    {
    }

    public function visit(Delivery $delivery, PricingRuleSet $ruleSet, ManualSupplements|null $manualSupplements = null): PriceCalculationOutput
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
            $resultPerTask = $this->visitTask($task, $ruleSet, $delivery);
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
        $resultPerDelivery = $this->visitDelivery($delivery, $ruleSet, $manualSupplements);
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
            $productVariants = $this->onDemandDeliveryProductProcessor->process($taskProductVariants, $deliveryProductVariant);
        }

        $output = new PriceCalculationOutput($calculation, $matchedRules, $productVariants);

        if (count($productVariants) === 0) {
            $this->feeCalculationLogger->info(sprintf('No rule matched'), [
                'strategy' => $ruleSet->getStrategy(),
            ]);
        } else {
            $this->feeCalculationLogger->info(sprintf('Calculated price: %d', $output->getPrice()), [
                'strategy' => $ruleSet->getStrategy(),
            ]);
        }

        return $output;
    }

    private function visitDelivery(Delivery $delivery, PricingRuleSet $ruleSet, ManualSupplements|null $manualSupplements = null): Result
    {
        $deliveryAsExpressionLanguageValues = $this->deliveryExpressionLanguageVisitor->toExpressionLanguageValues($delivery);

        if ($ruleSet->getStrategy() === 'find') {
            return $this->processRuleSet($delivery, $deliveryAsExpressionLanguageValues, $delivery, $ruleSet, function (PricingRule $rule) {
                // LEGACY_TARGET_DYNAMIC is used for backward compatibility
                // for more info see PricingRule::LEGACY_TARGET_DYNAMIC
                return $rule->getTarget() === PricingRule::TARGET_DELIVERY || $rule->getTarget() === PricingRule::LEGACY_TARGET_DYNAMIC;
            }, true, $manualSupplements?->orderSupplements ?? []);
        }

        if ($ruleSet->getStrategy() === 'map') {
            $tasks = $delivery->getTasks();

            return $this->processRuleSet($delivery, $deliveryAsExpressionLanguageValues, $delivery, $ruleSet, function (PricingRule $rule) use ($tasks) {
                // LEGACY_TARGET_DYNAMIC is used for backward compatibility
                // for more info see PricingRule::LEGACY_TARGET_DYNAMIC
                return $rule->getTarget() === PricingRule::TARGET_DELIVERY || ($rule->getTarget() === PricingRule::LEGACY_TARGET_DYNAMIC && count($tasks) <= 2);
            }, false, $manualSupplements?->orderSupplements ?? []);
        }

        return new Result([]);
    }

    private function visitTask(Task $task, PricingRuleSet $ruleSet, Delivery $delivery): Result
    {
        $taskAsExpressionLanguageValues = $this->taskExpressionLanguageVisitor->toExpressionLanguageValues($task);

        if ($ruleSet->getStrategy() === 'find') {
            return $this->processRuleSet($task, $taskAsExpressionLanguageValues, $delivery, $ruleSet, function (PricingRule $rule) {
                return $rule->getTarget() === PricingRule::TARGET_TASK;
            }, true, []);
        }

        if ($ruleSet->getStrategy() === 'map') {
            $tasks = $delivery->getTasks();

            return $this->processRuleSet($task, $taskAsExpressionLanguageValues, $delivery, $ruleSet, function (PricingRule $rule) use ($tasks) {
                // LEGACY_TARGET_DYNAMIC is used for backward compatibility
                // for more info see PricingRule::LEGACY_TARGET_DYNAMIC
                return $rule->getTarget() === PricingRule::TARGET_TASK || ($rule->getTarget() === PricingRule::LEGACY_TARGET_DYNAMIC && count($tasks) > 2);
            }, false, []);
        }

        return new Result([]);
    }

    /**
     * Process a rule set for a given object (Delivery or Task)
     * @param ManualSupplement[] $manualOrderSupplements
     */
    private function processRuleSet(
        Delivery|Task $object,
        array $expressionLanguageValues,
        Delivery $delivery,
        PricingRuleSet $ruleSet,
        callable $shouldApplyRule,
        bool $returnOnFirstMatch,
        array $manualOrderSupplements = []
    ): Result {
        /** @var RuleResult[] $ruleResults */
        $ruleResults = [];
        /** @var ProductOptionValueWithQuantity[] $productOptionValues */
        $productOptionValues = [];

        foreach ($ruleSet->getRules() as $rule) {
            if ($shouldApplyRule($rule)) {
                $ruleResult = $this->processRule($rule, $expressionLanguageValues, $ruleSet);
                $ruleResults[$rule->getPosition()] = $ruleResult;

                if ($ruleResult->matched) {
                    $productOptionValueWithQuantity = $this->onDemandDeliveryProductProcessor->processPricingRule(
                        $rule,
                        $expressionLanguageValues,
                    );

                    $productOptionValues[] = $productOptionValueWithQuantity;

                    // For `find` strategy
                    if ($returnOnFirstMatch) {
                        break;
                    }
                }
            }
        }

        // Add manual supplements (phase 1: only for order objects)
        if ($object instanceof Delivery && count($manualOrderSupplements) > 0) {
            foreach ($manualOrderSupplements as $supplement) {
                $rule = $supplement->pricingRule;
                $quantity = $supplement->quantity;

                $ruleResult = new RuleResult($rule, true);
                $ruleResults[$rule->getPosition()] = $ruleResult;

                $this->feeCalculationLogger->info(sprintf('Matched manual rule "%s"', $rule->getName()), [
                    'target' => $rule->getTarget(),
                ]);

                $productOptionValues[] = $this->onDemandDeliveryProductProcessor->processPricingRule(
                    $rule,
                    [
                        'quantity' => $quantity,
                    ]
                );
            }
        }

        if (count($productOptionValues) > 0) {
            $productVariant = $this->productVariantFactory->createWithProductOptions(
                $this->productVariantNameGenerator->generateVariantName($object, $delivery),
                $productOptionValues,
                $ruleSet
            );
            return new Result($ruleResults, $productVariant);
        }

        // When no matches found
        return new Result($ruleResults);
    }

    /**
     * Process a single rule for a given object (Delivery or Task)
     */
    private function processRule(PricingRule $rule, array $expressionLanguageValues, PricingRuleSet $ruleSet): RuleResult
    {
        $matched = $rule->matches($expressionLanguageValues, $this->expressionLanguage);

        if ($matched) {
            $this->feeCalculationLogger->info(sprintf('Matched rule "%s"', $rule->getExpression()), [
                'strategy' => $ruleSet->getStrategy(),
                'target' => $rule->getTarget(),
            ]);
        }

        return new RuleResult($rule, $matched);
    }
}
