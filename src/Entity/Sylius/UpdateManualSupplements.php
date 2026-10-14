<?php

namespace AppBundle\Entity\Sylius;

use AppBundle\Entity\Delivery\PricingRuleSet;
use AppBundle\Pricing\ManualSupplements;
use AppBundle\Sylius\Product\ProductVariantInterface;

final class UpdateManualSupplements extends UsePricingRules
{
    /**
     * @param ProductVariantInterface[] $productVariants
     */
    public function __construct(
        ManualSupplements $manualSupplements = new ManualSupplements([]),
        public readonly array $productVariants = [],
        ?PricingRuleSet $pricingRuleSet = null,
    ) {
        parent::__construct($manualSupplements, $pricingRuleSet);
    }
}
