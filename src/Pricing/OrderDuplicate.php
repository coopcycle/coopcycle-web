<?php

namespace AppBundle\Pricing;

use AppBundle\Entity\Delivery;
use AppBundle\Entity\Delivery\PricingRuleSet;
use AppBundle\Entity\Sylius\ArbitraryPrice;

class OrderDuplicate
{

    public function __construct(
        public Delivery $delivery,
        public ArbitraryPrice|null $previousArbitraryPrice = null,
        public PricingRuleSet|null $previousPricingRuleSet = null,
    ) {
    }
}
