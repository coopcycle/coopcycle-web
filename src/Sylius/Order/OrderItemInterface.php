<?php

namespace AppBundle\Sylius\Order;

use AppBundle\Entity\ReusablePackaging;
use AppBundle\Sylius\Product\ProductVariantInterface;
use AppBundle\Sylius\Customer\CustomerInterface;
use Doctrine\Common\Collections\Collection;
use Sylius\Component\Order\Model\OrderItemInterface as BaseOrderItemInterface;

interface OrderItemInterface extends BaseOrderItemInterface
{
    public function getTaxTotal(): int;

    public function getVariant(): ?ProductVariantInterface;

    public function setVariant(?ProductVariantInterface $variant): void;

    public function getAdjustmentsSorted(?string $type = null): Collection;

    public function getCustomer(): ?CustomerInterface;

    public function setCustomer(?CustomerInterface $customer): void;

    public function hasOverridenLoopeatQuantityForPackaging(ReusablePackaging $packaging): bool;

    public function getOverridenLoopeatQuantityForPackaging(ReusablePackaging $packaging);
}
