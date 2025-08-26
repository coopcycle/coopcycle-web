<?php

namespace AppBundle\Pricing;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Delivery\PricingRuleSet;
use AppBundle\Entity\Sylius\UpdateManualSupplements;
use AppBundle\Sylius\Product\ProductVariantFactory;
use AppBundle\Sylius\Product\ProductVariantInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

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
        //TODO:
        //split into taskProductVariants and deliveryProductVariant
        // TaskProductVariants keep the same (manual supplements not supported)
        // DeliveryProductVariant clone an existing or create a new one (if there are manual supplements)

        // Possible situations (after recalculation):
        // 1. same number of product variants
        // 1.1. update manual supplements
        // 2. new product variants are added
        // 2.1. to keep only if it contain manual supplements
        // 3. product variants are removed
        // 3.1. copy previous variants if they contain anything except manual supplements

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

        //TODO:

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
        ProductVariantInterface|null $deliveryProductVariant,
        array $manualOrderSupplements = []
    ): ProductVariantInterface|null {
        /** @var ProductOptionValueWithQuantity[] $productOptionValues */
        $productOptionValues = [];

        // TODO: exclude previously added manual supplements
        if ($deliveryProductVariant) {
            foreach ($deliveryProductVariant->getOptionValues() as $productOptionValue) {
                $productOptionValues[] = new ProductOptionValueWithQuantity(
                    $productOptionValue,
                    $deliveryProductVariant->getQuantityForOptionValue($productOptionValue)
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
