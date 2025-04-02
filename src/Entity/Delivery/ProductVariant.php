<?php

namespace AppBundle\Entity\Delivery;

use Symfony\Component\Serializer\Annotation\Groups;

/**
 * a simplified version of Sylius OrderItem/ProductVariant/ProductOptions structure
 * migrate to Sylius in https://github.com/coopcycle/coopcycle/issues/261
 */
class ProductVariant
{

    /**
     * Set after the order is 'processed' and the price for each ProductVariant is calculated
     */
    private ?int $price = null;

    public function __construct(
        private readonly array $productOptions
    )
    {
    }

    /**
     * @return ProductOption[]
     */
    #[Groups(['pricing_deliveries'])]
    public function getProductOptions(): array
    {
        return $this->productOptions;
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
