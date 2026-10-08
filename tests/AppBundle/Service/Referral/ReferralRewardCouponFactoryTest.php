<?php

namespace Tests\AppBundle\Service\Referral;

use AppBundle\Entity\Referral\ReferralLevel;
use AppBundle\Entity\Sylius\Customer;
use AppBundle\Entity\Sylius\Promotion;
use AppBundle\Entity\Sylius\PromotionCoupon;
use AppBundle\Service\Promotion\CustomerRewardCouponFactory;
use AppBundle\Service\Referral\ReferralRewardCouponFactory;
use AppBundle\Service\SettingsManager;
use AppBundle\Sylius\Promotion\Action\DeliveryPercentageDiscountPromotionActionCommand;
use AppBundle\Sylius\Promotion\Action\FixedDiscountPromotionActionCommand;
use AppBundle\Sylius\Promotion\Action\PercentageDiscountPromotionActionCommand;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Sylius\Component\Promotion\Factory\PromotionCouponFactoryInterface;
use Sylius\Component\Promotion\Model\PromotionRule;
use Sylius\Component\Promotion\Repository\PromotionCouponRepositoryInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;

class ReferralRewardCouponFactoryTest extends TestCase
{
    use ProphecyTrait;

    private function createFactory(array $settings = []): ReferralRewardCouponFactory
    {
        $promotionFactory = $this->prophesize(FactoryInterface::class);
        $promotionFactory->createNew()->will(fn () => new Promotion());

        $promotionRuleFactory = $this->prophesize(FactoryInterface::class);
        $promotionRuleFactory->createNew()->will(fn () => new PromotionRule());

        $promotionCouponFactory = $this->prophesize(PromotionCouponFactoryInterface::class);
        $promotionCouponFactory->createForPromotion(Argument::any())->will(fn () => new PromotionCoupon());

        $promotionCouponRepository = $this->prophesize(PromotionCouponRepositoryInterface::class);
        $promotionCouponRepository->findOneBy(Argument::any())->willReturn(null);

        $entityManager = $this->prophesize(EntityManagerInterface::class);

        $settingsManager = $this->prophesize(SettingsManager::class);
        $settingsManager->get(Argument::any())->will(fn ($args) => $settings[$args[0]] ?? null);

        $customerRewardCouponFactory = new CustomerRewardCouponFactory(
            $promotionFactory->reveal(),
            $promotionRuleFactory->reveal(),
            $promotionCouponFactory->reveal(),
            $promotionCouponRepository->reveal(),
            $entityManager->reveal()
        );

        return new ReferralRewardCouponFactory(
            $customerRewardCouponFactory,
            $settingsManager->reveal()
        );
    }

    public function testReferrerRewardCouponIsInternal(): void
    {
        $referrer = new Customer();
        $referrer->setFullName('referrer');

        $level = new ReferralLevel();
        $level->setMinReferralCount(1);
        $level->setRewardType(FixedDiscountPromotionActionCommand::TYPE);
        $level->setRewardAmount(500);
        $level->setCouponValidityDays(30);
        $level->setUsageLimit(1);

        $coupon = $this->createFactory()->createReferrerRewardCoupon($referrer, $level);

        self::assertTrue($coupon->isInternal());
    }

    public function testReferredWelcomeCouponIsInternal(): void
    {
        $referred = new Customer();
        $referred->setFullName('referred');

        $coupon = $this->createFactory()->createReferredWelcomeCoupon($referred);

        self::assertTrue($coupon->isInternal());
    }

    /**
     * When the welcome coupon settings are completely unconfigured
     * (SettingsManager::get() returns null for everything, as it does until
     * an admin saves the form at least once), the reward type falls back to
     * free delivery -- a 100% delivery discount that's never null, unlike a
     * fixed/percentage amount would be.
     */
    public function testReferredWelcomeCouponDefaultsToFreeDeliveryWhenUnconfigured(): void
    {
        $referred = new Customer();
        $referred->setFullName('referred');

        $coupon = $this->createFactory()->createReferredWelcomeCoupon($referred);

        $actions = $coupon->getPromotion()->getActions();
        self::assertCount(1, $actions);
        self::assertSame(DeliveryPercentageDiscountPromotionActionCommand::TYPE, $actions->first()->getType());

        $configuration = $actions->first()->getConfiguration();
        self::assertSame(1.0, $configuration['percentage']);
    }

    /**
     * Regression test: if an admin explicitly configures the welcome coupon
     * as a fixed discount but leaves the amount itself unset, the promotion
     * action's amount must never be left as a literal null.
     * Fixed/PercentageDiscountPromotionActionCommand::execute() both gate on
     * isset($configuration[...]), which PHP treats as false for a key
     * explicitly set to null -- so a null amount doesn't fail loudly, it
     * silently turns the coupon into a no-op discount.
     */
    public function testReferredWelcomeCouponNeverHasANullAmountConfiguredForFixedDiscount(): void
    {
        $referred = new Customer();
        $referred->setFullName('referred');

        $coupon = $this->createFactory(['referral_welcome_reward_type' => FixedDiscountPromotionActionCommand::TYPE])
            ->createReferredWelcomeCoupon($referred);

        $actions = $coupon->getPromotion()->getActions();
        self::assertCount(1, $actions);

        $configuration = $actions->first()->getConfiguration();
        self::assertArrayHasKey('amount', $configuration);
        self::assertNotNull($configuration['amount']);
        self::assertTrue(isset($configuration['amount']));
    }

    /**
     * Unlike free delivery (which the platform absorbs itself), a
     * fixed/percentage discount eats into the order total the restaurant is
     * paid on -- decrase_platform_fee (sic) tells OrderFeeProcessor to make
     * the coop absorb most of that cost instead of the restaurant.
     */
    public function testFixedAndPercentageRewardsDecreasePlatformFee(): void
    {
        $referrer = new Customer();
        $referrer->setFullName('referrer');

        $fixedLevel = new ReferralLevel();
        $fixedLevel->setMinReferralCount(1);
        $fixedLevel->setRewardType(FixedDiscountPromotionActionCommand::TYPE);
        $fixedLevel->setRewardAmount(500);
        $fixedLevel->setCouponValidityDays(30);
        $fixedLevel->setUsageLimit(1);

        $fixedCoupon = $this->createFactory()->createReferrerRewardCoupon($referrer, $fixedLevel);
        $fixedConfiguration = $fixedCoupon->getPromotion()->getActions()->first()->getConfiguration();
        self::assertTrue($fixedConfiguration['decrase_platform_fee']);

        $percentageLevel = new ReferralLevel();
        $percentageLevel->setMinReferralCount(15);
        $percentageLevel->setRewardType(PercentageDiscountPromotionActionCommand::TYPE);
        $percentageLevel->setRewardPercentage(15);
        $percentageLevel->setCouponValidityDays(30);
        $percentageLevel->setUsageLimit(1);

        $percentageCoupon = $this->createFactory()->createReferrerRewardCoupon($referrer, $percentageLevel);
        $percentageConfiguration = $percentageCoupon->getPromotion()->getActions()->first()->getConfiguration();
        self::assertTrue($percentageConfiguration['decrase_platform_fee']);
    }

    public function testFreeDeliveryRewardHasNoDecreasePlatformFeeKey(): void
    {
        $referrer = new Customer();
        $referrer->setFullName('referrer');

        $level = new ReferralLevel();
        $level->setMinReferralCount(1);
        $level->setRewardType(DeliveryPercentageDiscountPromotionActionCommand::TYPE);
        $level->setCouponValidityDays(30);
        $level->setUsageLimit(1);

        $coupon = $this->createFactory()->createReferrerRewardCoupon($referrer, $level);
        $configuration = $coupon->getPromotion()->getActions()->first()->getConfiguration();

        self::assertArrayNotHasKey('decrase_platform_fee', $configuration);
    }

    public function testReferrerRewardCouponUsageLimitMatchesLevelForFreeDelivery(): void
    {
        $referrer = new Customer();
        $referrer->setFullName('referrer');

        $level = new ReferralLevel();
        $level->setMinReferralCount(15);
        $level->setRewardType(DeliveryPercentageDiscountPromotionActionCommand::TYPE);
        $level->setCouponValidityDays(30);
        $level->setUsageLimit(5);

        $coupon = $this->createFactory()->createReferrerRewardCoupon($referrer, $level);

        $actions = $coupon->getPromotion()->getActions();
        self::assertSame(['percentage' => 1.0], $actions->first()->getConfiguration());

        // The coupon is already scoped to this one customer via the
        // IsCustomerRule, so the per-customer limit must match the level's
        // usageLimit for a higher tier to actually grant more than one use.
        self::assertSame(5, $coupon->getUsageLimit());
        self::assertSame(5, $coupon->getPerCustomerUsageLimit());
    }
}
