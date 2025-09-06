<?php

namespace AppBundle\Entity\Sylius;

use AppBundle\Pricing\ManualSupplements;

class UsePricingRules implements PricingStrategy
{
    public function __construct(
        public readonly ManualSupplements $manualSupplements = new ManualSupplements([])
    ) {
    }
}
