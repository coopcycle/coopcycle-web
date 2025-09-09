<?php

namespace AppBundle\Entity\Sylius;

use AppBundle\Pricing\ManualSupplements;

final class CalculateUsingPricingRules extends UsePricingRules
{
    public function __construct(
        ManualSupplements $manualSupplements = new ManualSupplements([])
    ) {
        parent::__construct($manualSupplements);
    }
}
