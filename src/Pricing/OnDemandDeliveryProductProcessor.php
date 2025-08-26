<?php

namespace AppBundle\Pricing;

use AppBundle\Entity\Delivery\PricingRule;
use AppBundle\Entity\Sylius\ProductOptionValue;
use AppBundle\Sylius\Product\ProductOptionValueInterface;
use AppBundle\Sylius\Product\ProductVariantInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

class OnDemandDeliveryProductProcessor
{
    public function __construct(
        private readonly ExpressionLanguage $expressionLanguage,
        private LoggerInterface $logger = new NullLogger()
    ) {
    }

    public function processProductOptionValue(
        ProductOptionValue $productOptionValue,
        PricingRule $rule,
        array $expressionLanguageValues,
    ): ProductOptionValueWithQuantity {
        $result = $rule->apply($expressionLanguageValues, $this->expressionLanguage);

        $this->logger->info(
            sprintf(
                'processProductOptionValue; result %d (rule "%s")',
                $result,
                $rule->getExpression()
            ),
            [
                'target' => $rule->getTarget(),
            ]
        );

        //FIXME: update when we properly model unit price and quantity in https://github.com/coopcycle/coopcycle/issues/441
        // currently we set price to 1 cent and quantity to the actual price, so that the total is price * quantity
        $basePrice = 1;

        // If the price is negative, we set the base price to -1 as the quantity can't be negative
        if ($result < 0) {
            $basePrice = -1;
            $result = abs($result);
        }

        // If the percentage is below 100% (10000 = 100.00%), we set the base price to -1 as it's a discount
        if ('CPCCL-ODDLVR-PERCENTAGE' === $productOptionValue->getOptionCode() && $result < 10000) {
            $basePrice = -1;
        }

        $productOptionValue->setPrice($basePrice);

        return new ProductOptionValueWithQuantity($productOptionValue, $result);
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
            if ('CPCCL-ODDLVR-PERCENTAGE' === $productOptionValue->getOptionCode()) {
                // for percentage-based rules: the price is calculated on the subtotal of the previous steps

                $priceMultiplier = $productVariant->getQuantityForOptionValue($productOptionValue);

                $previousSubtotal = $subtotal;

                $subtotal = (int)ceil($subtotal * ($priceMultiplier / 100 / 100));
                $price = $subtotal - $previousSubtotal;

                $this->logger->info(
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
}
