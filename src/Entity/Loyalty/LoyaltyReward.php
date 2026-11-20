<?php

namespace AppBundle\Entity\Loyalty;

use AppBundle\Sylius\Promotion\Action\DeliveryPercentageDiscountPromotionActionCommand;

/**
 * One entry in the admin-configurable catalogue customers spend their points
 * on. Reuses the referral program's reward types, free delivery included and
 * defaulted to for the same reason: the platform absorbs a delivery discount
 * itself, whereas a discount on the order total eats into the restaurant's
 * revenue.
 */
class LoyaltyReward
{
    protected ?int $id = null;

    protected string $name;

    protected int $position = 0;

    protected int $pointsCost;

    protected string $rewardType = DeliveryPercentageDiscountPromotionActionCommand::TYPE;

    protected ?int $rewardAmount = null;

    protected ?float $rewardPercentage = null;

    protected int $couponValidityDays = 30;

    protected bool $enabled = true;

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

    public function getPointsCost(): int
    {
        return $this->pointsCost;
    }

    public function setPointsCost(int $pointsCost): void
    {
        $this->pointsCost = $pointsCost;
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

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }
}
