<?php

namespace Tests\AppBundle\Service\Referral;

use AppBundle\Entity\Referral\ReferralLevel;
use AppBundle\Entity\Sylius\Customer;
use AppBundle\Entity\Sylius\Promotion;
use AppBundle\Entity\Sylius\PromotionCoupon;
use AppBundle\Service\Referral\ReferralRewardCouponFactory;
use AppBundle\Service\SettingsManager;
use AppBundle\Sylius\Promotion\Action\FixedDiscountPromotionActionCommand;
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

    private function createFactory(): ReferralRewardCouponFactory
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
        $settingsManager->get(Argument::any())->willReturn(null);

        return new ReferralRewardCouponFactory(
            $promotionFactory->reveal(),
            $promotionRuleFactory->reveal(),
            $promotionCouponFactory->reveal(),
            $promotionCouponRepository->reveal(),
            $entityManager->reveal(),
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
}
