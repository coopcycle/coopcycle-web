<?php

namespace AppBundle\Pricing;

use AppBundle\Entity\Delivery\PricingRule;
use AppBundle\Sylius\Order\OrderInterface;

class PriceCalculationOutput
{
    /**
     * @param PricingRule[] $matchedRules
     */
    public function __construct(
        public readonly ?Calculation $calculation,
        public readonly array $matchedRules,
        public readonly ?OrderInterface $order)
    {
    }

    public function getPrice(): ?int
    {
        return $this->order?->getItemsTotal();
    }
}
