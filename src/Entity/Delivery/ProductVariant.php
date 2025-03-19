<?php

namespace AppBundle\Entity\Delivery;

// a simplified version of Sylius OrderItem/ProductVariant/ProductOptions structure
// migration to Sylius later on
class ProductVariant
{
    public function __construct(
        private readonly string $matchedRule,
        private readonly int $priceAdditive,
        private readonly int $priceMultiplier)
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
