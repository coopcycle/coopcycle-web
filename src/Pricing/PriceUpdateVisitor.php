<?php

namespace AppBundle\Pricing;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Delivery\PricingRuleSet;
use AppBundle\Entity\Sylius\UpdateManualSupplements;
use AppBundle\Sylius\Product\ProductVariantInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class PriceUpdateVisitor
{
    public function __construct(
        private ProductVariantNameGenerator $productVariantNameGenerator,
        private LoggerInterface $logger = new NullLogger()
    )
    {
    }

    public function visit(Delivery $delivery, PricingRuleSet $ruleSet, UpdateManualSupplements $pricingStrategy): PriceCalculationOutput
    {
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
        $deliveryProductVariants = array_filter($pricingStrategy->productVariants, function ($item) {
            return $this->productVariantNameGenerator->isDeliveryProductVariant($item);
        });
        /**
         * @var ProductVariantInterface|null $deliveryProductVariant
         */
        $deliveryProductVariant = array_shift($deliveryProductVariants);

        //TODO:

//        // Apply the rules to the whole delivery/order
//        $resultPerDelivery = $this->visitDelivery($delivery, $ruleSet, $manualSupplements);
//        $resultPerDelivery->setDelivery($delivery);
//
//        $matchedRulesPerDelivery = array_filter($resultPerDelivery->ruleResults, function ($item) {
//            return $item->matched === true;
//        });
//        if (count($matchedRulesPerDelivery) > 0) {
//            $deliveryProductVariant = $resultPerDelivery->productVariant;
//        }
//
//        /**
//         * @var ProductVariantInterface[] $productVariants
//         */
//        $productVariants = $this->process($taskProductVariants, $deliveryProductVariant);
//
//        $output = new PriceCalculationOutput(null, [], $productVariants);
//
//        return $output;
    }
}
