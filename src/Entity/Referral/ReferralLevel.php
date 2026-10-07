<?php

namespace AppBundle\Entity\Referral;

use AppBundle\Sylius\Promotion\Action\DeliveryPercentageDiscountPromotionActionCommand;

/**
 * Admin-editable tier config (Bronze/Silver/Gold by default). The referrer's
 * level is never stored on the referrer -- it's resolved at read time from
 * their successful-referral count against these thresholds, see
 * AppBundle\Service\Referral\ReferralLevelResolver, so edits here never go
 * stale and never need a data migration.
 *
 * Free delivery is the default reward type: it's a cost the platform itself
 * absorbs, whereas a fixed/percentage discount on the order total eats into
 * the restaurant's revenue.
 */
class ReferralLevel
{
    protected ?int $id = null;

    protected string $name;

    protected int $position = 0;

    protected int $minReferralCount;

    protected string $rewardType = DeliveryPercentageDiscountPromotionActionCommand::TYPE;

    protected ?int $rewardAmount = null;

    protected ?float $rewardPercentage = null;

    protected int $couponValidityDays = 30;

    protected ?int $usageLimit = 1;

    protected ?\DateTime $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function getMinReferralCount(): int
    {
        return $this->minReferralCount;
    }

    public function setMinReferralCount(int $minReferralCount): void
    {
        $this->minReferralCount = $minReferralCount;
    }

    public function getRewardType(): string
    {
        return $this->rewardType;
    }

    public function setRewardType(string $rewardType): void
    {
        $this->rewardType = $rewardType;
    }

    public function getRewardAmount(): ?int
    {
        return $this->rewardAmount;
    }

    public function setRewardAmount(?int $rewardAmount): void
    {
        $this->rewardAmount = $rewardAmount;
    }

    public function getRewardPercentage(): ?float
    {
        return $this->rewardPercentage;
    }

    public function setRewardPercentage(?float $rewardPercentage): void
    {
        $this->rewardPercentage = $rewardPercentage;
    }

    public function getCouponValidityDays(): int
    {
        return $this->couponValidityDays;
    }

    public function setCouponValidityDays(int $couponValidityDays): void
    {
        $this->couponValidityDays = $couponValidityDays;
    }

    public function getUsageLimit(): ?int
    {
        return $this->usageLimit;
    }

    public function setUsageLimit(?int $usageLimit): void
    {
        $this->usageLimit = $usageLimit;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }
}
