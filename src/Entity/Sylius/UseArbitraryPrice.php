<?php

namespace AppBundle\Entity\Sylius;

class UseArbitraryPrice implements PricingStrategy
{
    public function __construct(
        private readonly ArbitraryPrice $arbitraryPrice
    )
    {
    }

    public function getArbitraryPrice(): ArbitraryPrice
    {
        return $this->arbitraryPrice;
    }
}
