<?php

namespace AppBundle\Entity\Sylius;

use AppBundle\Entity\Delivery\PricingRuleSet;
use AppBundle\Pricing\ManualSupplements;

final class CalculateUsingPricingRules extends UsePricingRules
{
    public function __construct(
        ManualSupplements $manualSupplements = new ManualSupplements([]),
        ?PricingRuleSet $pricingRuleSet = null,
    ) {
        parent::__construct($manualSupplements, $pricingRuleSet);
    }
}
