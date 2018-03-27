<?php

namespace AppBundle\Sylius\Order;

use AppBundle\Sylius\Product\ProductVariantInterface;
use Sylius\Component\Order\Model\OrderItemInterface as BaseOrderItemInterface;

interface OrderItemInterface extends BaseOrderItemInterface
{
    /**
     * @return int
     */
    public function getTaxTotal(): int;

    /**
     * @return ProductVariantInterface|null
     */
    public function getVariant(): ?ProductVariantInterface;

    /**
     * @param ProductVariantInterface|null $variant
     */
    public function setVariant(?ProductVariantInterface $variant): void;
}
