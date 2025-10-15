<?php

namespace AppBundle\Pricing;

use AppBundle\Entity\Delivery\PricingRule;
use AppBundle\Entity\Sylius\ProductOptionRepository;
use AppBundle\Entity\Sylius\ProductOptionValue;
use AppBundle\ExpressionLanguage\PriceEvaluation;
use AppBundle\Pricing\PriceExpressions\FixedPriceExpression;
use AppBundle\Pricing\PriceExpressions\PricePercentageExpression;
use AppBundle\Pricing\PriceExpressions\PricePerPackageExpression;
use AppBundle\Pricing\PriceExpressions\PriceRangeExpression;
use AppBundle\Sylius\Product\ProductOptionValueFactory;
use AppBundle\Sylius\Product\ProductOptionValueInterface;
use AppBundle\Sylius\Product\ProductVariantInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

class OnDemandDeliveryProductProcessor
{
    public function __construct(
        private readonly ProductOptionValueFactory $productOptionValueFactory,
        private readonly RuleHumanizer $ruleHumanizer,
        private readonly ExpressionLanguage $expressionLanguage,
        private readonly PriceExpressionParser $priceExpressionParser,
        private readonly LoggerInterface $feeCalculationLogger = new NullLogger()
    ) {
    }

    /**
     * @return ProductOptionValueWithQuantity[]
     */
    public function processPricingRule(
        PricingRule $rule,
        array $expressionLanguageValues,
    ): array {
        $productOptionValuesWithQuantity = [];

        /**
         * In the following code we work with the following concepts:
         * Product option values, price expressions, and price evaluation results.
         *
         * Normally each of them have a corresponding type,
         * but we check the following invariants to spot a potential error early:
         * 1. The number of product option values matches the number of evaluation results
         * 2. The price expression type matches the evaluation result type
         */

        $productOptionValues = $this->getProductOptionValues($rule);

        $priceExpression = $this->priceExpressionParser->parsePrice($rule->getPrice());
        $result = $rule->apply($expressionLanguageValues, $this->expressionLanguage);

        switch (get_class($priceExpression)) {
            case FixedPriceExpression::class:
                if (count($productOptionValues) !== 1) {
                    $this->feeCalculationLogger->warning('processProductOptionValue; incompatible number of product option values', [
                        'rule' => $rule->getPrice(),
                        'result' => $result,
                        'productOptionValues' => count($productOptionValues),
                    ]);
                    return [];
                }

                if (is_array($result) || $result instanceof PriceEvaluation) {
                    $this->feeCalculationLogger->warning('processProductOptionValue; unsupported result type', [
                        'rule' => $rule->getPrice(),
                        'result' => $result,
                    ]);
                    return [];
                }
                $productOptionValuesWithQuantity = $this->processFixedPriceExpression($rule, $result, $productOptionValues[0]);
                break;
            case PricePercentageExpression::class:
                if (count($productOptionValues) !== 1) {
                    $this->feeCalculationLogger->warning('processProductOptionValue; incompatible number of product option values', [
                        'rule' => $rule->getPrice(),
                        'result' => $result,
                        'productOptionValues' => count($productOptionValues),
                    ]);
                    return [];
                }

                if (is_array($result) || $result instanceof PriceEvaluation) {
                    $this->feeCalculationLogger->warning('processProductOptionValue; unsupported result type', [
                        'rule' => $rule->getPrice(),
                        'result' => $result,
                    ]);
                    return [];
                }
                $productOptionValuesWithQuantity = $this->processPricePercentageExpression($rule, $result, $productOptionValues[0]);
                break;
            case PriceRangeExpression::class:
                if (count($productOptionValues) !== 1) {
                    $this->feeCalculationLogger->warning('processProductOptionValue; incompatible number of product option values', [
                        'rule' => $rule->getPrice(),
                        'result' => $result,
                        'productOptionValues' => count($productOptionValues),
                    ]);
                    return [];
                }

                if (is_array($result)) {
                    $this->feeCalculationLogger->warning('processProductOptionValue; unsupported result type', [
                        'rule' => $rule->getPrice(),
                        'result' => $result,
                    ]);
                    return [];
                }
                if (!($result instanceof PriceEvaluation)) {
                    if (0 !== $result) {
                        $this->feeCalculationLogger->warning('processProductOptionValue; unsupported result type', [
                            'rule' => $rule->getPrice(),
                            'result' => $result,
                        ]);
                        return [];
                    }

                    // 0 is a valid value that means that the rule does not apply
                    break;
                }

                $productOptionValuesWithQuantity = $this->processPriceRangeExpression($rule, $result, $productOptionValues[0]);
                break;
            case PricePerPackageExpression::class:
                //the number of product option values must be greater or equal to the number of evaluation results
                if (is_array($result) && count($productOptionValues) < count($result)) {
                    $this->feeCalculationLogger->warning('processProductOptionValue; evaluation result (array) does not match the number of product option values', [
                        'rule' => $rule->getPrice(),
                        'result' => $result,
                        'productOptionValues' => count($productOptionValues),
                    ]);
                    return [];
                } elseif (!is_array($result) && count($productOptionValues) < 1) {
                    $this->feeCalculationLogger->warning('processProductOptionValue; evaluation result (PriceEvaluation) does not match the number of product option values', [
                        'rule' => $rule->getPrice(),
                        'result' => $result,
                        'productOptionValues' => count($productOptionValues),
                    ]);
                    return [];
                }

                if (!is_array($result) && !($result instanceof PriceEvaluation)) {
                    if (0 !== $result) {
                        $this->feeCalculationLogger->warning('processProductOptionValue; unsupported result type', [
                            'rule' => $rule->getPrice(),
                            'result' => $result,
                        ]);
                        return [];
                    }

                    // 0 is a valid value that means that the rule does not apply
                    break;
                }

                $productOptionValuesWithQuantity = $this->processPricePerPackageExpression($rule, $result, $productOptionValues);
                break;
            default:
                $this->feeCalculationLogger->warning('processProductOptionValue; unsupported result type', [
                    'rule' => $rule->getPrice(),
                    'result' => $result,
                ]);
                return [];
        }

        foreach ($productOptionValuesWithQuantity as $productOptionValueWithQuantity) {
            $this->feeCalculationLogger->info(
                sprintf(
                    'processProductOptionValue; quantity %d (rule "%s")',
                    $productOptionValueWithQuantity->quantity,
                    $rule->getExpression()
                ),
                [
                    'target' => $rule->getTarget(),
                ]
            );
        }

        return $productOptionValuesWithQuantity;
    }

    /**
     * @return ProductOptionValue[]
     */
    private function getProductOptionValues(
        PricingRule $rule,
    ): array {
        $productOptionValues = $rule->getProductOptionValues()->toArray();

        // Create a product option if none is defined
        if (0 === count($productOptionValues)) {
            $productOptionValues = $this->productOptionValueFactory->createForPricingRule(
                $rule,
                $this->ruleHumanizer->humanize($rule)
            );
        }

        foreach ($productOptionValues as $productOptionValue) {
            // Generate a default name if none is defined
            if (is_null($productOptionValue->getValue()) || '' === trim(
                    $productOptionValue->getValue()
                )) {
                $name = $this->ruleHumanizer->humanize($rule);
                $productOptionValue->setValue($name);
            }
        }

        return $productOptionValues;
    }

    /**
     * @param ProductVariantInterface[] $taskProductVariants
     * @return ProductVariantInterface[]
     */
    public function process(
        array $taskProductVariants,
        ?ProductVariantInterface $deliveryProductVariant
    ): array {
        $taskItemsTotal = 0;

        foreach ($taskProductVariants as $productVariant) {
            $this->processProductVariant($productVariant, 0);

            $taskItemsTotal += $productVariant->getOptionValuesPrice();
        }

        if ($deliveryProductVariant) {
            $this->processProductVariant($deliveryProductVariant, $taskItemsTotal);
        }

        return array_merge(
            $taskProductVariants,
            $deliveryProductVariant ? [$deliveryProductVariant] : []
        );
    }

    private function processProductVariant(
        ProductVariantInterface $productVariant,
        int $previousItemsTotal
    ): void {
        $subtotal = $previousItemsTotal;

        /**
         * @var ProductOptionValueInterface $productOptionValue
         */
        foreach ($productVariant->getOptionValues() as $productOptionValue) {
            if (ProductOptionRepository::PRODUCT_OPTION_CODE_PRICE_PERCENTAGE === $productOptionValue->getOptionCode()) {
                // for percentage-based rules: the price is calculated on the subtotal of the previous steps

                $priceMultiplier = $productVariant->getQuantityForOptionValue($productOptionValue);

                $previousSubtotal = $subtotal;

                $subtotal = (int)ceil($subtotal * ($priceMultiplier / 100 / 100));
                $price = $subtotal - $previousSubtotal;

                $this->feeCalculationLogger->info(
                    sprintf(
                        'processProductVariant; update percentage-based ProductOptionValue price to %d',
                        $price
                    ),
                    [
                        'base' => $previousSubtotal,
                        'percentage' => $priceMultiplier / 100 - 100,
                    ]
                );

                // Negative price (discount) is taken care of by setting a base price of -1 in processProductOptionValue
                $productVariant->addOptionValueWithQuantity($productOptionValue, abs($price));
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

    /**
     * @return ProductOptionValueWithQuantity[]
     */
    private function processFixedPriceExpression(
        PricingRule $rule,
        int $result,
        ProductOptionValue $productOptionValue
    ): array {

        // handle legacy product option values that might still hold an out-of-date unit price (format)
        // all newly created product option values should have the same price as return by the rule evaluation
        if ($productOptionValue->getPrice() !== $result) {
            $this->feeCalculationLogger->warning('processProductOptionValue; unit price does not match; updating', [
                'rule' => $rule->getPrice(),
                'expected' => $result,
                'actual' => $productOptionValue->getPrice(),
            ]);
            $productOptionValue->setPrice($result);
        }

        return [new ProductOptionValueWithQuantity($productOptionValue, 1)];
    }

    /**
     * @return ProductOptionValueWithQuantity[]
     */
    private function processPricePercentageExpression(
        PricingRule $rule,
        int $result,
        ProductOptionValue $productOptionValue
    ): array {

        // handle legacy product option values that might still hold an out-of-date unit price (format)
        // all newly created product option values should have the correct price already set
        if (abs($productOptionValue->getPrice()) !== 1) {
            $this->feeCalculationLogger->warning('processProductOptionValue; unit price does not match; updating', [
                'rule' => $rule->getPrice(),
                'actual' => $productOptionValue->getPrice(),
            ]);
            $productOptionValue->setPrice($result < PricePercentageExpression::PERCENTAGE_NEUTRAL ? -1 : 1);
        }

        // temporarily set quantity to percentage (will be updated later in calculation)
        return [new ProductOptionValueWithQuantity($productOptionValue, $result)];
    }

    /**
     * @return ProductOptionValueWithQuantity[]
     */
    private function processPriceRangeExpression(
        PricingRule $rule,
        PriceEvaluation $result,
        ProductOptionValue $productOptionValue
    ): array {
        // handle legacy product option values that might still hold an out-of-date unit price (format)
        // all newly created product option values should have the same price as return by the rule evaluation
        if ($productOptionValue->getPrice() !== $result->unitPrice) {
            $this->feeCalculationLogger->warning('processProductOptionValue; unit price does not match; updating', [
                'rule' => $rule->getPrice(),
                'expected' => $result->unitPrice,
                'actual' => $productOptionValue->getPrice(),
            ]);
            $productOptionValue->setPrice($result->unitPrice);
        }

        return [new ProductOptionValueWithQuantity($productOptionValue, $result->quantity)];
    }

    /**
     * @param PriceEvaluation[]|PriceEvaluation $result
     * @param ProductOptionValue[] $productOptionValues
     * @return ProductOptionValueWithQuantity[]
     */
    private function processPricePerPackageExpression(
        PricingRule $rule,
        array|PriceEvaluation $result,
        array $productOptionValues
    ): array {
        if (is_array($result)) {
            $productOptionValuesWithQuantity = [];

            $productOptionValuesToMatch = array_merge($productOptionValues);
            $notFoundResults = [];

            // For each evaluation result, find a matching product option value (by unit price)
            foreach ($result as $item) {
                $isMatched = false;
                foreach ($productOptionValuesToMatch as $productOptionValue) {
                    if ($productOptionValue->getPrice() === $item->unitPrice) {
                        $isMatched = true;
                        $productOptionValuesWithQuantity[] = new ProductOptionValueWithQuantity($productOptionValue, $item->quantity);
                        $productOptionValuesToMatch = array_filter($productOptionValuesToMatch, function ($value) use ($productOptionValue) {
                            return $value !== $productOptionValue;
                        });
                        break;
                    }
                }

                if (!$isMatched) {
                    $notFoundResults[] = $item;
                }
            }

            // For not matched results, take the first product option value and update its price
            foreach ($notFoundResults as $item) {
                $productOptionValue = $productOptionValues[0];
                $this->feeCalculationLogger->warning('processProductOptionValue; unit price does not match; updating', [
                    'rule' => $rule->getPrice(),
                    'expected' => $item->unitPrice,
                    'actual' => $productOptionValue->getPrice(),
                ]);
                $productOptionValue->setPrice($item->unitPrice);

                $productOptionValuesWithQuantity[] = new ProductOptionValueWithQuantity($productOptionValue, $item->quantity);
                $productOptionValuesToMatch = array_filter($productOptionValuesToMatch, function ($value) use ($productOptionValue) {
                    return $value !== $productOptionValue;
                });
            }

            return $productOptionValuesWithQuantity;
        } else {
            $productOptionValue = $productOptionValues[0];

            // handle legacy product option values that might still hold an out-of-date unit price (format)
            // all newly created product option values should have the same price as return by the rule evaluation
            if ($productOptionValue->getPrice() !== $result->unitPrice) {
                $this->feeCalculationLogger->warning('processProductOptionValue; unit price does not match; updating', [
                    'rule' => $rule->getPrice(),
                    'expected' => $result->unitPrice,
                    'actual' => $productOptionValue->getPrice(),
                ]);
                $productOptionValue->setPrice($result->unitPrice);
            }

            return [new ProductOptionValueWithQuantity($productOptionValue, $result->quantity)];
        }
    }
}
