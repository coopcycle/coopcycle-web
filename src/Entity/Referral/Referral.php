<?php

namespace AppBundle\Entity\Referral;

use AppBundle\Entity\Sylius\Customer;
use AppBundle\Sylius\Promotion\PromotionCouponInterface;
use Sylius\Component\Order\Model\OrderInterface;

/**
 * Tracks one referrer -> referred relationship, from the referred customer's
 * signup through to the first paid order that confirms the referral (or its
 * expiry if that order never comes).
 */
class Referral
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_EXPIRED = 'expired';

    protected ?int $id = null;

    protected Customer $referrer;

    protected Customer $referred;

    protected string $status = self::STATUS_PENDING;

    protected ?OrderInterface $triggeringOrder = null;

    protected ?PromotionCouponInterface $referrerRewardCoupon = null;

    protected ?PromotionCouponInterface $referredWelcomeCoupon = null;

    protected ?\DateTime $createdAt = null;

    protected ?\DateTime $completedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReferrer(): Customer
    {
        return $this->referrer;
    }

    public function setReferrer(Customer $referrer): void
    {
        $this->referrer = $referrer;
    }

    public function getReferred(): Customer
    {
        return $this->referred;
    }

    public function setReferred(Customer $referred): void
    {
        $this->referred = $referred;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function isPending(): bool
    {
        return self::STATUS_PENDING === $this->status;
    }

    public function getTriggeringOrder(): ?OrderInterface
    {
        return $this->triggeringOrder;
    }

    public function setTriggeringOrder(?OrderInterface $triggeringOrder): void
    {
        $this->triggeringOrder = $triggeringOrder;
    }

    public function getReferrerRewardCoupon(): ?PromotionCouponInterface
    {
        return $this->referrerRewardCoupon;
    }

    public function setReferrerRewardCoupon(?PromotionCouponInterface $referrerRewardCoupon): void
    {
        $this->referrerRewardCoupon = $referrerRewardCoupon;
    }

    public function getReferredWelcomeCoupon(): ?PromotionCouponInterface
    {
        return $this->referredWelcomeCoupon;
    }

    public function setReferredWelcomeCoupon(?PromotionCouponInterface $referredWelcomeCoupon): void
    {
        $this->referredWelcomeCoupon = $referredWelcomeCoupon;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function getCompletedAt(): ?\DateTime
    {
        return $this->completedAt;
    }

    public function markAsCompleted(OrderInterface $triggeringOrder): void
    {
        $this->status = self::STATUS_COMPLETED;
        $this->triggeringOrder = $triggeringOrder;
        $this->completedAt = new \DateTime();
    }

    public function markAsExpired(): void
    {
        $this->status = self::STATUS_EXPIRED;
    }
}
