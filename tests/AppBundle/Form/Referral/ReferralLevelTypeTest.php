<?php

namespace Tests\AppBundle\Form\Referral;

use AppBundle\Entity\Referral\ReferralLevel;
use AppBundle\Form\Referral\ReferralLevelType;
use AppBundle\Sylius\Promotion\Action\DeliveryPercentageDiscountPromotionActionCommand;
use AppBundle\Sylius\Promotion\Action\FixedDiscountPromotionActionCommand;
use AppBundle\Sylius\Promotion\Action\PercentageDiscountPromotionActionCommand;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

/**
 * Regression coverage for the validation that stops an admin saving a level
 * whose reward type doesn't match its configured amount/percentage -- see
 * ReferralRewardCouponFactoryTest for why an unconfigured amount is a real
 * (silent) bug, not just a cosmetic one.
 */
class ReferralLevelTypeTest extends TestCase
{
    use ProphecyTrait;

    public function testNoViolationWhenFixedTypeHasAnAmount(): void
    {
        $level = new ReferralLevel();
        $level->setRewardType(FixedDiscountPromotionActionCommand::TYPE);
        $level->setRewardAmount(500);

        $context = $this->prophesize(ExecutionContextInterface::class);
        $context->buildViolation(\Prophecy\Argument::any())->shouldNotBeCalled();

        (new ReferralLevelType())->validateRewardConfiguration($level, $context->reveal());
    }

    public function testViolationWhenFixedTypeHasNoAmount(): void
    {
        $level = new ReferralLevel();
        $level->setRewardType(FixedDiscountPromotionActionCommand::TYPE);
        $level->setRewardAmount(null);

        $builder = $this->prophesize(ConstraintViolationBuilderInterface::class);
        $builder->setTranslationDomain('messages')->willReturn($builder->reveal());
        $builder->atPath('rewardAmount')->willReturn($builder->reveal());
        $builder->addViolation()->shouldBeCalledOnce();

        $context = $this->prophesize(ExecutionContextInterface::class);
        $context->buildViolation('referral.level.field.rewardAmount.required_for_fixed')
            ->willReturn($builder->reveal())
            ->shouldBeCalledOnce();

        (new ReferralLevelType())->validateRewardConfiguration($level, $context->reveal());
    }

    public function testNoViolationForFreeDeliveryWithNoAmountOrPercentage(): void
    {
        $level = new ReferralLevel();
        $level->setRewardType(DeliveryPercentageDiscountPromotionActionCommand::TYPE);
        $level->setRewardAmount(null);
        $level->setRewardPercentage(null);

        $context = $this->prophesize(ExecutionContextInterface::class);
        $context->buildViolation(\Prophecy\Argument::any())->shouldNotBeCalled();

        (new ReferralLevelType())->validateRewardConfiguration($level, $context->reveal());
    }

    public function testViolationWhenPercentageTypeHasNoPercentage(): void
    {
        $level = new ReferralLevel();
        $level->setRewardType(PercentageDiscountPromotionActionCommand::TYPE);
        $level->setRewardPercentage(null);

        $builder = $this->prophesize(ConstraintViolationBuilderInterface::class);
        $builder->setTranslationDomain('messages')->willReturn($builder->reveal());
        $builder->atPath('rewardPercentage')->willReturn($builder->reveal());
        $builder->addViolation()->shouldBeCalledOnce();

        $context = $this->prophesize(ExecutionContextInterface::class);
        $context->buildViolation('referral.level.field.rewardPercentage.required_for_percentage')
            ->willReturn($builder->reveal())
            ->shouldBeCalledOnce();

        (new ReferralLevelType())->validateRewardConfiguration($level, $context->reveal());
    }
}
