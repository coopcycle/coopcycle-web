<?php

namespace AppBundle\Entity\Delivery;

use Symfony\Component\Serializer\Annotation\Groups;

/**
 * a simplified version of Sylius OrderItem/ProductVariant/ProductOptions structure
 * migrate to Sylius in https://github.com/coopcycle/coopcycle/issues/261
 */
class ProductOption
{
    /**
     * Set after the order is 'processed' and the price for each ProductVariant/ProductOption is calculated
     */
    private ?int $price = null;

    public function __construct(
        private readonly PricingRule $matchedRule,
        private readonly int $priceAdditive, // in cents
        private readonly int $priceMultiplier = 10000 // 1 => 0.01%; 10000 => 100%
    )
    {
    }

    #[Groups(['pricing_deliveries'])]
    public function getMatchedRule(): PricingRule
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

    #[Groups(['pricing_deliveries'])]
    public function getPrice(): ?int
    {
        return $this->price;
    }

    public function setPrice(int $price): void
    {
        $this->price = $price;
    }
}
