<?php

namespace AppBundle\Entity\Sylius;

class PricingRulesBasedPrice implements PriceInterface
{
    public function __construct(
        private readonly int $price,
    )
    {
    }

    public function getValue(): int
    {
        return $this->price;
    }
}
