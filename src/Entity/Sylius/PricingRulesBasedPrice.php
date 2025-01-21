<?php

namespace AppBundle\Entity\Sylius;

use AppBundle\Entity\Delivery\PricingRuleSet;

class PricingRulesBasedPrice implements PriceInterface
{
    public function __construct(
        private readonly int $price,
        // Allow to be null for backwards compatibility, in the new code we should always set it
        private readonly ?PricingRuleSet $pricingRuleSet = null
    )
    {
    }

    public function getValue(): int
    {
        return $this->price;
    }

    public function getPricingRuleSet(): ?PricingRuleSet
    {
        return $this->pricingRuleSet;
    }
}
