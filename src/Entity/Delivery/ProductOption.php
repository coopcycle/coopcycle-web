<?php

namespace AppBundle\Entity\Delivery;

use Symfony\Component\Serializer\Annotation\Groups;

/**
 * a simplified version of Sylius OrderItem/ProductVariant/ProductOptions structure
 * migrate to Sylius later on
 */
class ProductOption
{
    /**
     * Set after the order is 'processed' and the price for each ProductVariant/ProductOption is calculated
     */
    private ?int $price = null;

    public function __construct(
        private readonly string $matchedRule,
        private readonly string $priceRule,
        private readonly int $priceAdditive, // in cents
        private readonly int $priceMultiplier = 10000 // 0.01% - 1; 100% - 10000
    )
    {
    }

    /**
     * @Groups({"pricing_deliveries"})
     */
    public function getMatchedRule(): string
    {
        return $this->matchedRule;
    }

    /**
     * @Groups({"pricing_deliveries"})
     */
    public function getPriceRule(): string
    {
        return $this->priceRule;
    }

    public function getPriceAdditive(): int
    {
        return $this->priceAdditive;
    }

    public function getPriceMultiplier(): int
    {
        return $this->priceMultiplier;
    }

    /**
     * @Groups({"pricing_deliveries"})
     */
    public function getPrice(): ?int
    {
        return $this->price;
    }

    public function setPrice(int $price): void
    {
        $this->price = $price;
    }
}
