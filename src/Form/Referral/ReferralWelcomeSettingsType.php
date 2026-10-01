<?php

namespace AppBundle\Form\Referral;

use AppBundle\Sylius\Promotion\Action\FixedDiscountPromotionActionCommand;
use AppBundle\Sylius\Promotion\Action\PercentageDiscountPromotionActionCommand;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

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
                'choices' => [
                    'referral.reward_type.fixed' => FixedDiscountPromotionActionCommand::TYPE,
                    'referral.reward_type.percentage' => PercentageDiscountPromotionActionCommand::TYPE,
                ],
            ])
            ->add('referral_welcome_reward_amount', IntegerType::class, [
                'label' => 'referral.welcome_settings.field.rewardAmount',
                'required' => false,
                'constraints' => [new Assert\PositiveOrZero()],
            ])
            ->add('referral_welcome_reward_percentage', NumberType::class, [
                'label' => 'referral.welcome_settings.field.rewardPercentage',
                'required' => false,
                'constraints' => [new Assert\Range(min: 0, max: 100)],
            ])
            ->add('referral_welcome_coupon_validity_days', IntegerType::class, [
                'label' => 'referral.welcome_settings.field.couponValidityDays',
                'constraints' => [new Assert\Positive()],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
