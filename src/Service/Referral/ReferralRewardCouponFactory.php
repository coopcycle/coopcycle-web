<?php

namespace AppBundle\Service\Referral;

use AppBundle\Entity\Referral\ReferralLevel;
use AppBundle\Entity\Sylius\Customer;
use AppBundle\Sylius\Promotion\Action\FixedDiscountPromotionActionCommand;
use AppBundle\Sylius\Promotion\Action\PercentageDiscountPromotionActionCommand;
use AppBundle\Sylius\Promotion\Checker\Rule\IsCustomerRuleChecker;
use AppBundle\Sylius\Promotion\Checker\Rule\IsFirstOrderRuleChecker;
use AppBundle\Sylius\Promotion\Generator\CouponCodeAlphabet;
use AppBundle\Sylius\Promotion\PromotionCouponInterface;
use AppBundle\Service\SettingsManager;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Sylius\Component\Promotion\Factory\PromotionCouponFactoryInterface;
use Sylius\Component\Promotion\Model\PromotionAction;
use Sylius\Component\Promotion\Repository\PromotionCouponRepositoryInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;

/**
 * Mints the throwaway Promotion + single PromotionCoupon pair behind a
 * referral reward -- one bespoke pair per event, not a shared pool, since
 * UnambiguousPromotionCouponGenerator is built for bulk-generating
 * interchangeable codes under one promotion. Pattern copied from
 * AppBundle\Form\Model\Promotion::toPromotion().
 */
class ReferralRewardCouponFactory
{
    public function __construct(
        private readonly FactoryInterface $promotionFactory,
        private readonly FactoryInterface $promotionRuleFactory,
        private readonly PromotionCouponFactoryInterface $promotionCouponFactory,
        private readonly PromotionCouponRepositoryInterface $promotionCouponRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly SettingsManager $settingsManager)
    {
    }

    public function createReferrerRewardCoupon(Customer $referrer, ReferralLevel $level): PromotionCouponInterface
    {
        return $this->createCoupon(
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
        return $this->createCoupon(
            name: sprintf('Referral welcome - %s', $referred->getUsername()),
            customer: $referred,
            rewardType: $this->settingsManager->get('referral_welcome_reward_type') ?: FixedDiscountPromotionActionCommand::TYPE,
            amount: null !== ($amount = $this->settingsManager->get('referral_welcome_reward_amount')) ? (int) $amount : null,
            percentage: null !== ($percentage = $this->settingsManager->get('referral_welcome_reward_percentage')) ? (float) $percentage : null,
            validityDays: (int) ($this->settingsManager->get('referral_welcome_coupon_validity_days') ?: 30),
            usageLimit: 1,
            firstOrderOnly: true,
        );
    }

    private function createCoupon(
        string $name,
        Customer $customer,
        string $rewardType,
        ?int $amount,
        ?float $percentage,
        int $validityDays,
        ?int $usageLimit,
        bool $firstOrderOnly): PromotionCouponInterface
    {
        $promotion = $this->promotionFactory->createNew();
        $promotion->setName($name);
        $promotion->setCouponBased(true);
        $promotion->setCode(Uuid::uuid4()->toString());
        $promotion->setPriority(1);

        $promotionAction = new PromotionAction();
        $promotionAction->setType($rewardType);
        // Fixed/PercentageDiscountPromotionActionCommand::execute() both gate
        // on isset($configuration[...]), which is false for an array key set
        // to null -- so an unconfigured amount/percentage wouldn't fail loudly,
        // it would silently turn the coupon into a no-op discount. Default to
        // 0 rather than ever write a null in here.
        $promotionAction->setConfiguration(
            PercentageDiscountPromotionActionCommand::TYPE === $rewardType
                ? ['percentage' => $percentage ?? 0.0]
                : ['amount' => $amount ?? 0]
        );
        $promotion->addAction($promotionAction);

        // Scope redemption to the specific customer, on top of the coupon's
        // own perCustomerUsageLimit, so a leaked code can't be redeemed by
        // someone else.
        $isCustomerRule = $this->promotionRuleFactory->createNew();
        $isCustomerRule->setType(IsCustomerRuleChecker::TYPE);
        $isCustomerRule->setConfiguration(['username' => $customer->getUsername()]);
        $promotion->addRule($isCustomerRule);

        if ($firstOrderOnly) {
            $isFirstOrderRule = $this->promotionRuleFactory->createNew();
            $isFirstOrderRule->setType(IsFirstOrderRuleChecker::TYPE);
            $isFirstOrderRule->setConfiguration([]);
            $promotion->addRule($isFirstOrderRule);
        }

        $promotionCoupon = $this->promotionCouponFactory->createForPromotion($promotion);
        $promotionCoupon->setCode($this->generateUniqueCode());
        $promotionCoupon->setPerCustomerUsageLimit(1);
        $promotionCoupon->setUsageLimit($usageLimit);
        $promotionCoupon->setExpiresAt(new \DateTime(sprintf('+%d days', $validityDays)));
        // Minted programmatically for a specific customer -- keep it out of
        // /admin/promotions and non-editable there, see PromotionCoupon::$internal.
        $promotionCoupon->setInternal(true);

        $promotion->addCoupon($promotionCoupon);

        $this->entityManager->persist($promotion);

        return $promotionCoupon;
    }

    private function generateUniqueCode(): string
    {
        do {
            $code = '';
            for ($i = 0; $i < 8; $i++) {
                $code .= CouponCodeAlphabet::ALPHABET[random_int(0, CouponCodeAlphabet::base() - 1)];
            }
        } while (null !== $this->promotionCouponRepository->findOneBy(['code' => $code]));

        return $code;
    }
}
