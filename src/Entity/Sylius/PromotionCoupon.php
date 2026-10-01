<?php

namespace AppBundle\Entity\Sylius;

use Sylius\Component\Promotion\Model\PromotionCoupon as BasePromotionCoupon;
use AppBundle\Sylius\Promotion\PromotionCouponInterface;

class PromotionCoupon extends BasePromotionCoupon implements PromotionCouponInterface
{
    /** @var int|null */
    protected $perCustomerUsageLimit;

    /** @var bool */
    protected $featured = false;

    /**
     * Coupons minted programmatically by another feature (e.g. the referral
     * program's per-customer reward/welcome coupons) rather than created by
     * an admin -- excluded from /admin/promotions and not editable there,
     * since an admin tweaking a one-off, already-issued reward coupon would
     * silently change what a specific customer was promised.
     */
    protected bool $internal = false;

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

    /**
     * {@inheritdoc}
     */
    public function setFeatured($featured = true): void
    {
        $this->featured = $featured;
    }

    /**
     * {@inheritdoc}
     */
    public function isFeatured(): bool
    {
        return $this->featured;
    }

    public function isInternal(): bool
    {
        return $this->internal;
    }

    public function setInternal(bool $internal): void
    {
        $this->internal = $internal;
    }
}
