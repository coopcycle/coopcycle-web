<?php

namespace AppBundle\Pricing;

use AppBundle\Entity\Delivery\PricingRuleSet;

class Calculation
{
    /**
     * @param PricingRuleSet $ruleSet
     * @param Result[] $resultsPerEntity
     */
    public function __construct(
        public readonly PricingRuleSet $ruleSet,
        public readonly array $resultsPerEntity
    ) {
    }
}
