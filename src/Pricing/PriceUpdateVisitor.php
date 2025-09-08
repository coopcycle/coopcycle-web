<?php

namespace AppBundle\Pricing;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Delivery\PricingRuleSet;
use AppBundle\Entity\Sylius\UpdateManualSupplements;
use AppBundle\Sylius\Product\ProductVariantFactory;
use AppBundle\Sylius\Product\ProductVariantInterface;

class PriceUpdateVisitor
{
    public function __construct(
        private readonly ProductOptionValueHelper $productOptionValueHelper,
        private readonly ProductVariantFactory $productVariantFactory,
        private readonly ProductVariantNameGenerator $productVariantNameGenerator,
        private readonly OnDemandDeliveryProductProcessor $onDemandDeliveryProductProcessor
    ) {
    }

    public function visit(
        Delivery $delivery,
        PricingRuleSet $ruleSet,
        UpdateManualSupplements $pricingStrategy
    ): PriceCalculationOutput {
        //Split into taskProductVariants and deliveryProductVariant
        // TaskProductVariants keep the same (manual supplements not supported)
        // DeliveryProductVariant update an existing or create a new product variant

        /**
         * @var ProductVariantInterface[] $taskProductVariants
         */
        $taskProductVariants = array_filter($pricingStrategy->productVariants, function ($item) {
            return !$this->productVariantNameGenerator->isDeliveryProductVariant($item);
        });

        // normally there is only one delivery product variant or none
        $deliveryProductVariants = array_filter(
            $pricingStrategy->productVariants,
            function ($item) {
                return $this->productVariantNameGenerator->isDeliveryProductVariant($item);
            }
        );
        /**
         * @var ProductVariantInterface|null $deliveryProductVariant
         */
        $deliveryProductVariant = array_shift($deliveryProductVariants);

        // Apply the rules to the whole delivery/order
        $deliveryProductVariant = $this->visitDelivery(
            $delivery,
            $ruleSet,
            $deliveryProductVariant,
            $pricingStrategy->manualSupplements->orderSupplements
        );

        /**
         * @var ProductVariantInterface[] $productVariants
         */
        $productVariants = $this->onDemandDeliveryProductProcessor->process(
            $taskProductVariants,
            $deliveryProductVariant
        );

        $output = new PriceCalculationOutput(null, [], $productVariants);

        return $output;
    }

    /**
     * @param ManualSupplement[] $manualOrderSupplements
     */
    private function visitDelivery(
        Delivery $delivery,
        PricingRuleSet $ruleSet,
        ProductVariantInterface|null $previousDeliveryProductVariant,
        array $manualOrderSupplements = []
    ): ProductVariantInterface|null {
        /** @var ProductOptionValueWithQuantity[] $productOptionValues */
        $productOptionValues = [];

        // Possible scenarios:
        // 1. $previousDeliveryProductVariant exists => clone without manual supplements & re-add manual supplements
        // 2. $previousDeliveryProductVariant does not exist => create

        if ($previousDeliveryProductVariant) {
            // clone productOptionValues except previously added manual supplements
            foreach ($previousDeliveryProductVariant->getOptionValues() as $productOptionValue) {
                if ($productOptionValue->getPricingRule()?->isManualSupplement()) {
                    continue;
                }

                $productOptionValues[] = new ProductOptionValueWithQuantity(
                    $productOptionValue,
                    $previousDeliveryProductVariant->getQuantityForOptionValue($productOptionValue)
                );
            }
        }


        // Add manual supplements (phase 1: only for order objects)
        if (count($manualOrderSupplements) > 0) {
            foreach ($manualOrderSupplements as $supplement) {
                $rule = $supplement->pricingRule;
                //TODO; handle with range-based supplements in https://github.com/coopcycle/coopcycle/issues/447
//                $quantity = $supplement->quantity;

                $productOptionValue = $this->productOptionValueHelper->getProductOptionValue($rule);
                $productOptionValues[] = $this->onDemandDeliveryProductProcessor->processProductOptionValue(
                    $productOptionValue,
                    $rule,
                    []
                );
            }
        }

        if (count($productOptionValues) > 0) {
            $productVariant = $this->productVariantFactory->createWithProductOptions(
                $this->productVariantNameGenerator->generateVariantName($delivery, $delivery),
                $productOptionValues,
                $ruleSet
            );

            return $productVariant;
        }

        return null;
    }
}
