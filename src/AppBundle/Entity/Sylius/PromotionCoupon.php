<?php

namespace AppBundle\Entity\Sylius;

use Sylius\Component\Promotion\Model\PromotionCoupon as BasePromotionCoupon;
use AppBundle\Sylius\Promotion\PromotionCouponInterface;

class PromotionCoupon extends BasePromotionCoupon implements PromotionCouponInterface
{
    /** @var int|null */
    protected $perCustomerUsageLimit;

    /**
     * {@inheritdoc}
     */
    public function getPerCustomerUsageLimit(): ?int
    {
        return $this->perCustomerUsageLimit;
    }

    /**
     * {@inheritdoc}
     */
    public function setPerCustomerUsageLimit(?int $perCustomerUsageLimit): void
    {
        $this->perCustomerUsageLimit = $perCustomerUsageLimit;
    }
}
