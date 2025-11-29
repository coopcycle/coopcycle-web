<?php

namespace AppBundle\Pricing;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Delivery\PricingRuleSet;
use AppBundle\Entity\Sylius\UpdateManualSupplements;
use AppBundle\Sylius\Product\ProductVariantFactory;
use AppBundle\Sylius\Product\ProductVariantInterface;
use Doctrine\ORM\EntityNotFoundException;

class PriceUpdateVisitor
{
    public function __construct(
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
                try {
                    // Find the PricingRule linked to this ProductOptionValue
                    $pricingRule = $productOptionValue->getPricingRule();
                } catch (EntityNotFoundException $e) {
                    // This happens when a pricing rule has been modified
                    // and the linked product option value has been disabled
                    // but is still attached to a product variant
                    $pricingRule = null;
                }

                if (is_null($pricingRule) || $pricingRule->isManualSupplement()) {
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
                $quantity = $supplement->quantity;

                $productOptionValueWithQuantity = $this->onDemandDeliveryProductProcessor->processPricingRule(
                    $rule,
                    [
                        'quantity' => $quantity,
                    ],
                );
                $productOptionValues = array_merge($productOptionValues, $productOptionValueWithQuantity);
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
