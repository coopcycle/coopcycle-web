<?php

namespace AppBundle\Service\Referral;

use AppBundle\Entity\Referral\ReferralLevel;
use AppBundle\Entity\Sylius\Customer;
use AppBundle\Service\Promotion\CustomerRewardCouponFactory;
use AppBundle\Service\SettingsManager;
use AppBundle\Sylius\Promotion\Action\DeliveryPercentageDiscountPromotionActionCommand;
use AppBundle\Sylius\Promotion\PromotionCouponInterface;

/**
 * Turns referral configuration -- a level, or the global welcome settings --
 * into the arguments CustomerRewardCouponFactory mints a coupon from.
 */
class ReferralRewardCouponFactory
{
    public function __construct(
        private readonly CustomerRewardCouponFactory $customerRewardCouponFactory,
        private readonly SettingsManager $settingsManager)
    {
    }

    public function createReferrerRewardCoupon(Customer $referrer, ReferralLevel $level): PromotionCouponInterface
    {
        return $this->customerRewardCouponFactory->create(
            name: sprintf('Referral reward - %s', $referrer->getUsername()),
            customer: $referrer,
            rewardType: $level->getRewardType(),
            amount: $level->getRewardAmount(),
            percentage: $level->getRewardPercentage(),
            validityDays: $level->getCouponValidityDays(),
            usageLimit: $level->getUsageLimit(),
            firstOrderOnly: false,
        );
    }

    public function createReferredWelcomeCoupon(Customer $referred): PromotionCouponInterface
    {
        return $this->customerRewardCouponFactory->create(
            name: sprintf('Referral welcome - %s', $referred->getUsername()),
            customer: $referred,
            rewardType: $this->settingsManager->get('referral_welcome_reward_type') ?: DeliveryPercentageDiscountPromotionActionCommand::TYPE,
            amount: null !== ($amount = $this->settingsManager->get('referral_welcome_reward_amount')) ? (int) $amount : null,
            percentage: null !== ($percentage = $this->settingsManager->get('referral_welcome_reward_percentage')) ? (float) $percentage : null,
            validityDays: (int) ($this->settingsManager->get('referral_welcome_coupon_validity_days') ?: 30),
            usageLimit: 1,
            firstOrderOnly: true,
        );
    }
}
