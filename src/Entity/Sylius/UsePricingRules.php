<?php

namespace AppBundle\Entity\Sylius;

use AppBundle\Entity\Delivery\PricingRuleSet;
use AppBundle\Pricing\ManualSupplements;

class UsePricingRules implements PricingStrategy
{
    /**
     * @param PricingRuleSet|null $pricingRuleSet The rule set chosen by a dispatcher, if any;
     *                                            see PricingManager::resolvePricingRuleSet
     */
    public function __construct(
        public readonly ManualSupplements $manualSupplements = new ManualSupplements([]),
        public readonly ?PricingRuleSet $pricingRuleSet = null,
    ) {
    }
}
