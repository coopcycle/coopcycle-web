<?php

namespace AppBundle\Entity\Delivery;

/**
 * a simplified version of Sylius OrderItem/ProductVariant/ProductOptions structure
 * migrate to Sylius in https://github.com/coopcycle/coopcycle/issues/261
 */
class Order
{

    /**
     * Set after the order is 'processed' and the price for each ProductVariant is calculated
     */
    private ?int $itemsTotal = null;

    /**
     * @param OrderItem[] $items
     */
    public function __construct(
        private readonly array $items,
    )
    {
    }

    public function getItems(): array
    {
        return $this->items;
    }

    public function getItemsTotal(): int
    {
        return $this->itemsTotal;
    }

    public function setItemsTotal(int $itemsTotal): void
    {
        $this->itemsTotal = $itemsTotal;
    }
}
