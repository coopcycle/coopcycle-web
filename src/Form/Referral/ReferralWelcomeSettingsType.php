<?php

namespace AppBundle\Form\Referral;

use AppBundle\Form\Type\MoneyType;
use AppBundle\Sylius\Promotion\Action\FixedDiscountPromotionActionCommand;
use AppBundle\Sylius\Promotion\Action\PercentageDiscountPromotionActionCommand;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Global config for the referred customer's "welcome" coupon -- plain
 * scalars stored through Craue/SettingsManager, same pattern as
 * RfmThresholdsType, since (unlike ReferralLevel) there's only ever one of
 * these.
 */
class ReferralWelcomeSettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('referral_welcome_reward_type', ChoiceType::class, [
                'label' => 'referral.welcome_settings.field.rewardType',
                'help' => 'referral.welcome_settings.field.rewardType.help',
                'choices' => [
                    'referral.reward_type.fixed' => FixedDiscountPromotionActionCommand::TYPE,
                    'referral.reward_type.percentage' => PercentageDiscountPromotionActionCommand::TYPE,
                ],
            ])
            ->add('referral_welcome_reward_amount', MoneyType::class, [
                'label' => 'referral.welcome_settings.field.rewardAmount',
                'help' => 'referral.welcome_settings.field.rewardAmount.help',
                'required' => false,
                'constraints' => [new Assert\PositiveOrZero()],
            ])
            ->add('referral_welcome_reward_percentage', NumberType::class, [
                'label' => 'referral.welcome_settings.field.rewardPercentage',
                'help' => 'referral.welcome_settings.field.rewardPercentage.help',
                'required' => false,
                'constraints' => [new Assert\Range(min: 0, max: 100)],
            ])
            ->add('referral_welcome_coupon_validity_days', IntegerType::class, [
                'label' => 'referral.welcome_settings.field.couponValidityDays',
                'help' => 'referral.welcome_settings.field.couponValidityDays.help',
                'constraints' => [new Assert\Positive()],
            ])
            ->add('referral_pending_ttl_days', IntegerType::class, [
                'label' => 'referral.welcome_settings.field.pendingTtlDays',
                'help' => 'referral.welcome_settings.field.pendingTtlDays.help',
                'constraints' => [new Assert\Positive()],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'constraints' => [new Assert\Callback([$this, 'validateRewardConfiguration'])],
        ]);
    }

    /**
     * Same rationale as ReferralLevelType::validateRewardConfiguration(): an
     * unconfigured amount/percentage would otherwise mint welcome coupons
     * that silently redeem for no discount.
     */
    public function validateRewardConfiguration(array $data, ExecutionContextInterface $context): void
    {
        $rewardType = $data['referral_welcome_reward_type'] ?? null;

        if (FixedDiscountPromotionActionCommand::TYPE === $rewardType && null === ($data['referral_welcome_reward_amount'] ?? null)) {
            $context->buildViolation('referral.welcome_settings.field.rewardAmount.required_for_fixed')
                ->setTranslationDomain('messages')
                ->atPath('referral_welcome_reward_amount')
                ->addViolation();
        }

        if (PercentageDiscountPromotionActionCommand::TYPE === $rewardType && null === ($data['referral_welcome_reward_percentage'] ?? null)) {
            $context->buildViolation('referral.welcome_settings.field.rewardPercentage.required_for_percentage')
                ->setTranslationDomain('messages')
                ->atPath('referral_welcome_reward_percentage')
                ->addViolation();
        }
    }
}
