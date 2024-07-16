<?php

namespace AppBundle\Entity\Sylius;

class UseArbitraryPrice implements PricingStrategy
{
    public function __construct(
        private readonly string $variantName,
        private readonly int $variantPrice,
    )
    {
    }

    public function getVariantName(): string
    {
        return $this->variantName;
    }

    public function getVariantPrice(): int
    {
        return $this->variantPrice;
    }
}
