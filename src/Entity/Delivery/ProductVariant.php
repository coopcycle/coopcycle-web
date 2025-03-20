<?php

namespace AppBundle\Entity\Delivery;

// a simplified version of Sylius OrderItem/ProductVariant/ProductOptions structure
// migration to Sylius later on
class ProductVariant
{
    public function __construct(
        private readonly string $matchedRule,
        private readonly int $priceAdditive, // in cents
        private readonly int $priceMultiplier = 10000 // 0.01% - 1; 100% - 10000
    )
    {
    }

    public function getMatchedRule(): string
    {
        return $this->matchedRule;
    }

    public function getPriceAdditive(): int
    {
        return $this->priceAdditive;
    }

    public function getPriceMultiplier(): int
    {
        return $this->priceMultiplier;
    }
}
