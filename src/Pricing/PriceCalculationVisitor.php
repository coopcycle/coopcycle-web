<?php

namespace AppBundle\Pricing;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Delivery\PricingRule;
use AppBundle\Entity\Delivery\PricingRuleSet;
use AppBundle\Entity\Sylius\ProductOptionValue;
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
use Symfony\Contracts\Translation\TranslatorInterface;

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
        private readonly RuleHumanizer $ruleHumanizer,
        private readonly TranslatorInterface $translator,
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
            return $this->applyFindStrategy($delivery, $deliveryAsExpressionLanguageValues, $delivery, $ruleSet, function (PricingRule $rule) {
                // LEGACY_TARGET_DYNAMIC is used for backward compatibility
                // for more info see PricingRule::LEGACY_TARGET_DYNAMIC
                return $rule->getTarget() === PricingRule::TARGET_DELIVERY || $rule->getTarget() === PricingRule::LEGACY_TARGET_DYNAMIC;
            });
        }

        if ($ruleSet->getStrategy() === 'map') {
            $tasks = $delivery->getTasks();

            return $this->applyMapStrategy($delivery, $deliveryAsExpressionLanguageValues, $delivery, $ruleSet, function (PricingRule $rule) use ($tasks) {
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
            return $this->applyFindStrategy($task, $taskAsExpressionLanguageValues, $delivery, $ruleSet, function (PricingRule $rule) {
                return $rule->getTarget() === PricingRule::TARGET_TASK;
            });
        }

        if ($ruleSet->getStrategy() === 'map') {
            $tasks = $delivery->getTasks();

            return $this->applyMapStrategy($task, $taskAsExpressionLanguageValues, $delivery, $ruleSet, function (PricingRule $rule) use ($tasks) {
                // LEGACY_TARGET_DYNAMIC is used for backward compatibility
                // for more info see PricingRule::LEGACY_TARGET_DYNAMIC
                return $rule->getTarget() === PricingRule::TARGET_TASK || ($rule->getTarget() === PricingRule::LEGACY_TARGET_DYNAMIC && count($tasks) > 2);
            });
        }

        return new Result([]);
    }

    private function applyFindStrategy(Delivery|Task $object, array $expressionLanguageValues, Delivery $delivery, PricingRuleSet $ruleSet, $shouldApplyRule): Result
    {
        /** @var RuleResult[] $ruleResults */
        $ruleResults = [];
        /** @var ProductOptionValueWithQuantity[] $productOptionValues */
        $productOptionValues = [];

        foreach ($ruleSet->getRules() as $rule) {
            if ($shouldApplyRule($rule)) {
                $matched = $rule->matches($expressionLanguageValues, $this->expressionLanguage);

                $ruleResults[$rule->getPosition()] = new RuleResult($rule, $matched);

                if ($matched) {
                    $this->logger->info(sprintf('Matched rule "%s"', $rule->getExpression()), [
                        'strategy' => $ruleSet->getStrategy(),
                        'target' => $rule->getTarget(),
                    ]);

                    $productOptionValue = $this->getProductOptionValue($rule);
                    $ProductOptionValueWithQuantity = $this->processProductOptionValue(
                        $productOptionValue,
                        $rule,
                        $expressionLanguageValues,
                        $this->expressionLanguage
                    );

                    $productOptionValues[] = $ProductOptionValueWithQuantity;

                    $productVariant = $this->productVariantFactory->createWithProductOptions($this->generateVariantName($object, $delivery), $productOptionValues, $ruleSet);

                    return new Result($ruleResults, $productVariant);
                }
            }
        }

        return new Result($ruleResults);
    }

    private function applyMapStrategy(Delivery|Task $object, array $expressionLanguageValues, Delivery $delivery, PricingRuleSet $ruleSet, $shouldApplyRule): Result
    {
        /** @var RuleResult[] $ruleResults */
        $ruleResults = [];
        /** @var ProductOptionValueWithQuantity[] $productOptionValues */
        $productOptionValues = [];

        foreach ($ruleSet->getRules() as $rule) {
            if ($shouldApplyRule($rule)) {
                $matched = $rule->matches($expressionLanguageValues, $this->expressionLanguage);

                $ruleResults[$rule->getPosition()] = new RuleResult($rule, $matched);

                if ($matched) {
                    $this->logger->info(sprintf('Matched rule "%s"', $rule->getExpression()), [
                        'strategy' => $ruleSet->getStrategy(),
                        'target' => $rule->getTarget(),
                    ]);

                    $productOptionValue = $this->getProductOptionValue($rule);
                    $ProductOptionValueWithQuantity = $this->processProductOptionValue(
                        $productOptionValue,
                        $rule,
                        $expressionLanguageValues,
                        $this->expressionLanguage
                    );

                    $productOptionValues[] = $ProductOptionValueWithQuantity;
                }
            }
        }

        $matchedRules = array_filter($ruleResults, function ($item) {
            return $item->matched === true;
        });

        if (count($matchedRules) > 0) {
            $productVariant = $this->productVariantFactory->createWithProductOptions($this->generateVariantName($object, $delivery), $productOptionValues, $ruleSet);

            return new Result($ruleResults, $productVariant);
        } else {
            return new Result($ruleResults);
        }
    }

    private function getProductOptionValue(
        PricingRule $rule,
    ): ProductOptionValue {

        $productOptionValue = $rule->getProductOptionValue();

        // Create a product option if none is defined
        if (is_null($productOptionValue)) {
            $productOptionValue = $this->productOptionValueFactory->createForPricingRule($rule, $this->ruleHumanizer->humanize($rule));
        } else {
            //FIXME: for now, we need to make sure to create a new entity for each calculation
            // as we set the calculated price on the entity itself
            // when we properly implement quantities and product option types (percentage) we can
            // make ProductOptionValues immutable and remove this
            $productOptionValue = $this->productOptionValueFactory->createForPricingRule($rule, $rule->getName());
        }

        // Generate a default name if none is defined
        if (is_null($productOptionValue->getName()) || '' === trim($productOptionValue->getName())) {
            $name = $this->ruleHumanizer->humanize($rule);
            $productOptionValue->setValue($name);
        }

        return $productOptionValue;
    }

    private function processProductOptionValue(
        ProductOptionValue $productOptionValue,
        PricingRule $rule,
        array $expressionLanguageValues,
        ?ExpressionLanguage $language = null
    ): ProductOptionValueWithQuantity {
        if (null === $language) {
            $language = new ExpressionLanguage();
        }

        $result = $rule->apply($expressionLanguageValues, $language);

        $this->logger->info(sprintf('processProductOptionValue; result %d (rule "%s")', $result, $rule->getExpression()), [
            'target' => $rule->getTarget(),
        ]);

        //For now; km and package-based rules will contain total in $price
        // return price per km or package and quantity separately?
        $productOptionValue->setPrice($result);

        return new ProductOptionValueWithQuantity($productOptionValue, 1);
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

            $taskItemsTotal += $productVariant->getOptionValuesPrice();
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

            if ('CPCCL-ODDLVR-PERCENTAGE' === $productOptionValue->getOptionCode()) {
                // for percentage-based rules: the price is calculated on the subtotal of the previous steps

                $priceMultiplier = $productOptionValue->getPrice();

                $previousSubtotal = $subtotal;

                $subtotal = (int)ceil($subtotal * ($priceMultiplier / 100 / 100));
                $price = $subtotal - $previousSubtotal;

                $this->logger->info(sprintf('processProductVariant; update percentage-based ProductOptionValue price to %d', $price), [
                    'base' => $previousSubtotal,
                    'percentage' => $priceMultiplier / 100 - 100,
                ]);

                $productOptionValue->setPrice($price);

            } else {
                $quantity = $productVariant->getQuantityForOptionValue($productOptionValue);
                $subtotal += $productOptionValue->getPrice() * $quantity;
            }
        }

        // On Demand Delivery product variant price is set as follows:
        // 1. productVariant price (unit price) is set to 0
        // 2. Product option values prices are added to the order via adjustments in OrderOptionsProcessor
        $productVariant->setPrice(0);
    }

    private function generateVariantName(Delivery|Task $object, Delivery $delivery): string
    {
        if ($object instanceof Delivery) {
            return $this->translator->trans('pricing.variant.order_supplement');
        }

        $taskType = $object->getType();
        $taskPosition = $this->getTaskPositionByType($delivery, $object);

        if ($taskType === Task::TYPE_PICKUP) {
            return $this->translator->trans('pricing.variant.pickup_point', ['%number%' => $taskPosition]);
        } elseif ($taskType === Task::TYPE_DROPOFF) {
            return $this->translator->trans('pricing.variant.dropoff_point', ['%number%' => $taskPosition]);
        } else {
            throw new \InvalidArgumentException(sprintf('Unknown task type: %s', $taskType));
        }
    }

    private function getTaskPositionByType(Delivery $delivery, Task $task): int
    {
        $tasks = $delivery->getTasks();
        $taskType = $task->getType();
        $position = 1;

        foreach ($tasks as $deliveryTask) {
            if ($deliveryTask->getType() === $taskType) {
                if ($deliveryTask === $task) {
                    return $position;
                }
                $position++;
            }
        }

        return $position;
    }
}
