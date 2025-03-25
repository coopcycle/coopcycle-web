<?php

namespace AppBundle\Entity\Delivery;

use Symfony\Component\Serializer\Annotation\Groups;

/**
 * a simplified version of Sylius OrderItem/ProductVariant/ProductOptions structure
 * migrate to Sylius later on
 */
class OrderItem
{

    /**
     * Set after the order is 'processed' and the price for each ProductVariant is calculated
     */
    private ?int $total = null;

    public function __construct(
        private readonly ProductVariant $productVariant,
    )
    {
    }

    /**
     * @Groups({"pricing_deliveries"})
     */
    public function getProductVariant(): ProductVariant
    {
        return $this->productVariant;
    }

    /**
     * @Groups({"pricing_deliveries"})
     */
    public function getTotal(): ?int
    {
        return $this->total;
    }

    public function setTotal(?int $total): void
    {
        $this->total = $total;
    }
}
