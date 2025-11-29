<?php

namespace AppBundle\Pricing;

use AppBundle\Entity\Delivery\PricingRule;
use Symfony\Component\Serializer\Annotation\Groups;

class RuleResult
{
    public function __construct(
        #[Groups(['pricing_deliveries'])]
        public readonly PricingRule $rule,
        #[Groups(['pricing_deliveries'])]
        public readonly bool $matched
    ) {
    }
}
