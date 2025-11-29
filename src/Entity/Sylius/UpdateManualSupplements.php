<?php

namespace AppBundle\Entity\Sylius;

use AppBundle\Pricing\ManualSupplements;
use AppBundle\Sylius\Product\ProductVariantInterface;

final class UpdateManualSupplements extends UsePricingRules
{
    /**
     * @param ProductVariantInterface[] $productVariants
     */
    public function __construct(
        ManualSupplements $manualSupplements = new ManualSupplements([]),
        public readonly array $productVariants = []
    ) {
        parent::__construct($manualSupplements);
    }
}
