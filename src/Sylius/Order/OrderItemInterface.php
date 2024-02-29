<?php

namespace AppBundle\Sylius\Order;

use AppBundle\Entity\ReusablePackaging;
use AppBundle\Sylius\Product\ProductVariantInterface;
use AppBundle\Sylius\Customer\CustomerInterface;
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

    /**
     * @return CustomerInterface|null
     */
    public function getCustomer(): ?CustomerInterface;

    /**
     * @param CustomerInterface|null $customer
     */
    public function setCustomer(?CustomerInterface $customer): void;

    public function hasOverridenLoopeatQuantityForPackaging(ReusablePackaging $packaging): bool;

    public function getOverridenLoopeatQuantityForPackaging(ReusablePackaging $packaging);
}
