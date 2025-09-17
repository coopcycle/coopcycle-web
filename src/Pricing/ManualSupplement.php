<?php

namespace AppBundle\Pricing;

use AppBundle\Entity\Delivery\PricingRule;

class ManualSupplement
{
    public function __construct(
        public readonly PricingRule $pricingRule,
        public readonly int $quantity,
    ) {
    }
}
