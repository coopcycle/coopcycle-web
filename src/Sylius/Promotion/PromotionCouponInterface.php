<?php

namespace AppBundle\Sylius\Promotion;

use Sylius\Component\Promotion\Model\PromotionCouponInterface as BasePromotionCouponInterface;

interface PromotionCouponInterface extends BasePromotionCouponInterface
{
    public function getPerCustomerUsageLimit(): ?int;

    public function setPerCustomerUsageLimit(?int $perCustomerUsageLimit): void;
}
