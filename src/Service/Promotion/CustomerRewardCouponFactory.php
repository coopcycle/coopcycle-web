<?php

namespace AppBundle\Service\Promotion;

use AppBundle\Entity\Sylius\Customer;
use AppBundle\Sylius\Promotion\Action\DeliveryPercentageDiscountPromotionActionCommand;
use AppBundle\Sylius\Promotion\Action\PercentageDiscountPromotionActionCommand;
use AppBundle\Sylius\Promotion\Checker\Rule\IsCustomerRuleChecker;
use AppBundle\Sylius\Promotion\Checker\Rule\IsFirstOrderRuleChecker;
use AppBundle\Sylius\Promotion\Generator\CouponCodeAlphabet;
use AppBundle\Sylius\Promotion\PromotionCouponInterface;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Sylius\Component\Promotion\Factory\PromotionCouponFactoryInterface;
use Sylius\Component\Promotion\Model\PromotionAction;
use Sylius\Component\Promotion\Repository\PromotionCouponRepositoryInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;

/**
 * Mints the throwaway Promotion + single PromotionCoupon pair behind a reward
 * earned by one specific customer -- one bespoke pair per event, not a shared
 * pool, since UnambiguousPromotionCouponGenerator is built for
 * bulk-generating interchangeable codes under one promotion. Pattern copied
 * from AppBundle\Form\Model\Promotion::toPromotion().
 *
 * Shared by the referral and loyalty programs: the pitfalls encoded below
 * (null configuration silently disabling a discount, who absorbs the cost,
 * usage limits that don't apply) are subtle enough that a second copy of
 * them would be a liability.
 */
class CustomerRewardCouponFactory
{
    public function __construct(
        private readonly FactoryInterface $promotionFactory,
        private readonly FactoryInterface $promotionRuleFactory,
        private readonly PromotionCouponFactoryInterface $promotionCouponFactory,
        private readonly PromotionCouponRepositoryInterface $promotionCouponRepository,
        private readonly EntityManagerInterface $entityManager)
    {
    }

    public function create(
        string $name,
        Customer $customer,
        string $rewardType,
        ?int $amount,
        ?float $percentage,
        int $validityDays,
        ?int $usageLimit,
        bool $firstOrderOnly = false): PromotionCouponInterface
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
        $promotionAction->setConfiguration(match ($rewardType) {
            // Free delivery is always a full 100% off -- there's no
            // configurable amount/percentage for this reward type.
            DeliveryPercentageDiscountPromotionActionCommand::TYPE => ['percentage' => 1.0],
            // Fixed/percentage discounts eat into the order total, which
            // (unlike delivery) is shared with the restaurant -- decrase_platform_fee
            // (sic, see OrderFeeProcessor::decreasePlatformFee()) makes the
            // coop absorb most of the cost instead of the restaurant.
            PercentageDiscountPromotionActionCommand::TYPE => ['percentage' => $percentage ?? 0.0, 'decrase_platform_fee' => true],
            default => ['amount' => $amount ?? 0, 'decrase_platform_fee' => true],
        });
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
        // The coupon is already scoped to this one customer via the
        // IsCustomerRule above, so the per-customer and global limits are the
        // same number -- the per-customer one is what's actually enforced, so
        // leaving it at 1 would cap every reward at a single use regardless
        // of what was asked for here.
        $promotionCoupon->setPerCustomerUsageLimit($usageLimit);
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
