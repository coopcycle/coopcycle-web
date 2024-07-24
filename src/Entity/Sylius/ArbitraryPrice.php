<?php

namespace AppBundle\Entity\Sylius;

class ArbitraryPrice implements PriceInterface
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

    public function getValue(): int
    {
        return $this->variantPrice;
    }
}
